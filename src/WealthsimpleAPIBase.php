<?php

namespace PPFinances\Wealthsimple;

use PPFinances\Wealthsimple\Exceptions\CurlException;
use PPFinances\Wealthsimple\Exceptions\LoginFailedException;
use PPFinances\Wealthsimple\Exceptions\ManualLoginRequired;
use PPFinances\Wealthsimple\Exceptions\OTPRequiredException;
use PPFinances\Wealthsimple\Exceptions\UnexpectedException;
use PPFinances\Wealthsimple\Exceptions\WSApiException;
use PPFinances\Wealthsimple\Sessions\WSAPISession;

const LOAD_ALL_PAGES = TRUE;

abstract class WealthsimpleAPIBase
{
    protected const OAUTH_BASE_URL  = 'https://api.production.wealthsimple.com/v1/oauth/v2';
    protected const GRAPHQL_URL     = 'https://my.wealthsimple.com/graphql';
    protected const GRAPHQL_VERSION = '12';

    protected WSAPISession $session;

    protected static ?string $user_agent = NULL;

    public static function setUserAgent(string $user_agent) {
        static::$user_agent = $user_agent;
    }

    private static function uuidv4() : string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function sendHttpRequest(string $url, $method = 'POST', $data = NULL, array $headers = [], bool $return_headers = FALSE, bool $return_cookies = FALSE) {
        $ch = curl_init();

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }

        $is_oauth = strpos($url, static::OAUTH_BASE_URL) !== FALSE;
        if ($is_oauth) {
            if (!empty($this->session->session_id)) {
                $headers[] = "x-ws-session-id: {$this->session->session_id}";
            }
        }
        if (!empty($this->session->access_token) && @$data->grant_type !== 'refresh_token') {
            $headers[] = "Authorization: Bearer {$this->session->access_token}";
        }
        if (!empty($this->session->wssdi)) {
            $headers[] = "x-ws-device-id: {$this->session->wssdi}";
        }
        if (!empty(static::$user_agent)) {
            $headers[] = "User-Agent: " . static::$user_agent;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        if ($return_headers) {
            curl_setopt($ch, CURLOPT_HEADER, TRUE);
        }

        $cookie_jar = NULL;
        if ($return_cookies) {
            $cookie_jar = tempnam(sys_get_temp_dir(), 'wsapi_cookies_');
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
        }

        // The following command enables cURL's "auto encoding" mode, where it will announce to the server which encoding methods it supports (via the Accept-Encoding header), and then automatically decompress the response for you:
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $result = curl_exec($ch);

        if (!$result) {
            $errno = curl_errno($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($cookie_jar !== NULL) {
                @unlink($cookie_jar);
            }
            throw new CurlException("Error executing sendPOST($url); cURL error: $errno $err", $errno);
        }

        if ($return_cookies) {
            $cookies = self::parseCookieJar($cookie_jar);
            @unlink($cookie_jar);
            curl_close($ch);
            return (object) ['body' => $result, 'cookies' => $cookies];
        }

        curl_close($ch);

        return $result;
    }

    private static function parseCookieJar(string $path): array {
        $cookies = [];
        if (!file_exists($path) || filesize($path) === 0) {
            return $cookies;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === FALSE) {
            return $cookies;
        }
        foreach ($lines as $line) {
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            // Netscape cookie file format: domain  flag  path  secure  expiration  name  value
            $parts = preg_split('/\s+/', $line, 7);
            if (is_array($parts) && count($parts) >= 7) {
                $cookies[$parts[5]] = $parts[6];
            }
        }
        return $cookies;
    }

    private function sendGET(string $url, array $headers = [], bool $return_headers = FALSE, bool $return_cookies = FALSE) {
        return $this->sendHttpRequest($url, 'GET', NULL, $headers, $return_headers, $return_cookies);
    }

    private function sendPOST(string $url, $data, array $headers = [], bool $return_headers = FALSE, bool $return_cookies = FALSE) {
        return $this->sendHttpRequest($url, 'POST', $data, $headers, $return_headers, $return_cookies);
    }

    private function __construct(?object $session = NULL) {
        $this->startSession($session);
    }

    /**
     * Initialize the session, either from a stored session, or by creating a new session.
     * When creating a new session: get WSSDI (device ID), (Oauth) client ID, and generate a new (random) session ID.
     *
     * @param object|null $session Session object
     *
     * @return void
     * @throws UnexpectedException
     */
    private function startSession(?object $session = NULL) : void {
        $this->session = new WSAPISession();

        if ($session) {
            $this->session->access_token = $session->access_token;
            $this->session->wssdi = $session->wssdi;
            $this->session->session_id = $session->session_id;
            $this->session->client_id = $session->client_id;
            $this->session->refresh_token = $session->refresh_token;
        }

        if (empty($this->session->wssdi) || empty($this->session->client_id)) {
            $this->bootstrapDeviceIdAndClient();
        }

        if (empty($this->session->session_id)) {
            $this->session->session_id = $this->uuidv4();
        }
    }

    /**
     * Perform the unauthenticated bootstrap requests to obtain wssdi (device ID)
     * and client_id.
     *
     * Uses sendHttpRequest (with $return_cookies) so the configured user_agent
     * is applied and network errors are consistently wrapped as CurlException.
     * Prefers the cURL cookie jar (which correctly handles multiple Set-Cookie
     * headers) with a narrow fallback to the raw header parsing logic for
     * unusual environments.
     *
     * @return void
     * @throws UnexpectedException
     */
    private function bootstrapDeviceIdAndClient() : void {
        $app_js_url = NULL;

        if (empty($this->session->wssdi) || empty($this->session->client_id)) {
            // Fetch the login page via the wrapper for consistent headers + error handling.
            $resp = $this->sendGET('https://my.wealthsimple.com/app/login', [], FALSE, TRUE);

            // Preferred path: cookie jar (handles duplicates, path, domain, etc.)
            if (empty($this->session->wssdi) && isset($resp->cookies['wssdi'])) {
                $this->session->wssdi = $resp->cookies['wssdi'];
            }

            // Fallback: parse the raw Set-Cookie header string (last resort).
            if (empty($this->session->wssdi)) {
                foreach (explode("\n", str_replace("\r", "", $resp->body)) as $line) {
                    if (preg_match('/set-cookie:.*wssdi=([a-f0-9-]+);/i', $line, $re)) {
                        $this->session->wssdi = $re[1];
                        break;
                    }
                }
            }

            // Locate the main application bundle URL from the HTML.
            foreach (explode("\n", $resp->body) as $line) {
                if (preg_match('/<script.*src="(.+\/app-[a-f0-9]+.js)/i', $line, $re)) {
                    $app_js_url = $re[1];
                    break;
                }
            }

            if (!$this->session->wssdi) {
                throw new UnexpectedException("Couldn't find wssdi in login page response headers.");
            }
        }
        if (empty($this->session->client_id)) {
            if (empty($app_js_url)) {
                throw new UnexpectedException("Couldn't find app JS URL in login page response body.");
            }
            $resp = $this->sendGET($app_js_url, [], FALSE, TRUE);
            foreach (explode("\n", str_replace("\r", "", $resp->body)) as $line) {
                if (preg_match('/"production"[^}]*clientId:"([a-f0-9]+)"/i', $line, $re)) {
                    $this->session->client_id = $re[1];
                }
            }
            if (!$this->session->client_id) {
                throw new UnexpectedException("Couldn't find clientId in app JS.");
            }
        }
    }

    /**
     * Check if the OAuth token is still valid. If not, try to refresh it.
     *
     * @param callable|null $persist_session_fct Function to persist the session object, upon successful login. Will receive a two parameters: the session object, which you can JSON-encode to persist, and the username. Careful! This object contains sensitive tokens.
     * @param string|null   $username            Username to use for the session.
     *
     * @return void
     * @throws ManualLoginRequired If the OAuth token is invalid and cannot be refreshed.
     * @throws WSApiException
     */
    private function checkOAuthToken(?callable $persist_session_fct = NULL, ?string $username = NULL) : void {
        if (!empty($this->session->access_token)) {
            // Access token is working?
            try {
                $this->searchSecurity('XEQT');
                // OK; access_token works
                return;
            } catch (WSApiException $e) {
                if (@$e->response->message !== 'Not Authorized.' && @$e->response->errors[0]->message !== 'Not Authorized.') {
                    throw $e;
                }
                // Access token expired; try to refresh it below
            }
        }

        if (!empty($this->session->refresh_token)) {
            // Use refresh token to get a new access token
            $data = (object) [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->session->refresh_token,
                'client_id'     => $this->session->client_id,
            ];
            $headers = ["x-wealthsimple-client: @wealthsimple/wealthsimple", 'x-ws-profile: invest'];
            $response = $this->sendPOST(static::OAUTH_BASE_URL . '/token', $data, $headers);
            $response = json_decode($response);
            if (!empty($response->access_token)) {
                $this->session->access_token = $response->access_token;
                $this->session->refresh_token = $response->refresh_token;
                if ($persist_session_fct) {
                    $persist_session_fct($this->session, $username);
                }
                return;
            }
            // Failed to refresh access token
        }
        throw new ManualLoginRequired("Failed to use OAuth token. Manual login needed.");
    }

    public const SCOPE_READ_ONLY  = 'invest.read trade.read tax.read';
    public const SCOPE_READ_WRITE = 'invest.read trade.read tax.read invest.write trade.write tax.write';

    /**
     * Login on the Wealthsimple API using the provided credentials.
     *
     * @param string        $username            Wealthsimple username (email address)
     * @param string        $password            Password
     * @param string|null   $otp_answer          2FA code, if required
     * @param callable|null $persist_session_fct Function to persist the session object, upon successful login. Will receive a two parameters: the session object, which you can JSON-encode to persist, and the username. Careful! This object contains sensitive tokens.
     * @param string        $scope               Scope to request; See WealthsimpleAPI::SCOPE_*; Default to SCOPE_READ_ONLY.
     *
     * @return WSAPISession On success, the session object is returned. You should store this object in your database for future use.
     * @throws LoginFailedException
     * @throws OTPRequiredException
     */
    private function loginInternal(string $username, string $password, ?string $otp_answer = NULL, ?callable $persist_session_fct = NULL, string $scope = self::SCOPE_READ_ONLY) : WSAPISession {
        $data = (object) [
            'grant_type'     => 'password',
            'username'       => $username,
            'password'       => $password,
            'skip_provision' => 'true',
            'scope'          => $scope,
            'client_id'      => $this->session->client_id,
            'otp_claim'      => NULL,
        ];
        $headers = ["x-wealthsimple-client: @wealthsimple/wealthsimple", "x-ws-profile: undefined"];
        if (!empty($otp_answer)) {
            $headers[] = "x-wealthsimple-otp: $otp_answer;remember=true";
        }
        $response = $this->sendPOST(static::OAUTH_BASE_URL . '/token', $data, $headers);
        $response = json_decode($response);

        if (@$response->error === 'invalid_grant' && empty($otp_answer)) {
            throw new OTPRequiredException("2FA code required");
        }
        if (!empty($response->error)) {
            throw new LoginFailedException("Login failed", 0, $response);
        }

        $this->session->access_token = $response->access_token;
        $this->session->refresh_token = $response->refresh_token;

        if ($persist_session_fct) {
            $persist_session_fct($this->session, $username);
        }

        return $this->session;
    }

    protected static function dateFormatISO(?string $timestamp): ?string {
        if (empty($timestamp)) {
            return NULL;
        }
        return date('Y-m-d', strtotime($timestamp));
    }

    protected function doGraphQLQuery(string $query_name, array $variables, string $data_response_path, string $expect_type, ?callable $filter = NULL, bool $load_all_pages = FALSE) {
        $query = [
            'operationName' => $query_name,
            'query'         => static::getGraphQLQuery($query_name),
        ];
        if (!empty($variables)) {
            $query['variables'] = $variables;
        }

        $headers = [
            "x-ws-profile: trade",
            "x-ws-api-version: " . static::GRAPHQL_VERSION,
            "x-ws-locale: en-CA",
            "x-platform-os: web",
        ];
        $response = $this->sendPOST(static::GRAPHQL_URL, $query, $headers);
        $response = json_decode($response);

        if (!property_exists($response, 'data')) {
            throw new WSApiException("GraphQL query failed: $query_name", 0, $response);
        }

        $data = $response->data;
        $end_cursor = NULL;
        foreach (explode('.', $data_response_path) as $key) {
            if (!is_object($data) || !property_exists($data, $key)) {
                throw new WSApiException("GraphQL query failed: $query_name", 0, $response);
            }
            $data = $data->{$key};
            if (isset($data->pageInfo->hasNextPage) && $data->pageInfo->hasNextPage) {
                $end_cursor = $data->pageInfo->endCursor;
            }
        }

        if (($expect_type === 'array' && !is_array($data)) || ($expect_type === 'object' && !is_object($data))) {
            throw new WSApiException("GraphQL query failed: $query_name", 0, $response);
        }

        if ($key === 'edges') {
            // Extract nodes from edges
            $data = array_map(fn($edge) => $edge->node, $data);
        }

        if ($filter) {
            $data = array_filter($data, $filter);
        }

        if ($expect_type === 'array') {
            $data = array_values($data);
        }

        if ($load_all_pages) {
            if ($expect_type !== 'array') {
                throw new UnexpectedException("Can't load all pages for GraphQL queries that do not return arrays", 0);
            }
            if ($end_cursor) {
                $variables['cursor'] = $end_cursor;
                $more_data = $this->doGraphQLQuery($query_name, $variables, $data_response_path, $expect_type, $filter, TRUE);
                $data = array_merge($data, $more_data);
            }
        }

        return $data;
    }

    protected function getTokenInfo() {
        if (empty($this->session->token_info)) {
            $headers = ["x-wealthsimple-client: @wealthsimple/wealthsimple"];
            $response = $this->sendGET(static::OAUTH_BASE_URL . '/token/info', $headers);
            $this->session->token_info = json_decode($response);
        }
        return $this->session->token_info;
    }

    /**
     * Login on the Wealthsimple API using the provided credentials.
     *
     * @param string        $username            Email address
     * @param string        $password            Password
     * @param string|null   $otp_answer          2FA code, if required
     * @param callable|null $persist_session_fct Function to persist the session object, upon successful login.
     * @param string        $scope               Scope to request; See WealthsimpleAPI::SCOPE_*; Default to SCOPE_READ_ONLY.
     *
     * @return void
     * @throws LoginFailedException
     * @throws OTPRequiredException
     */
    public static function login(string $username, string $password, ?string $otp_answer = NULL, ?callable $persist_session_fct = NULL, string $scope = self::SCOPE_READ_ONLY) : WSAPISession {
        $ws = new WealthsimpleAPI();
        return $ws->loginInternal($username, $password, $otp_answer, $persist_session_fct, $scope);
    }

    /**
     * Access the Wealthsimple API using an existing session object, returned by a previous call to login().
     *
     * @param object        $session             Session object
     * @param callable|null $persist_session_fct Function to persist the session object, upon successful login. Will receive a two parameters: the session object, which you can JSON-encode to persist, and the username. Careful! This object contains sensitive tokens.
     * @param string|null   $username            Wealthsimple username (email address). Will be passed as the second parameter when calling $persist_session_fct.
     *
     * @return WealthsimpleAPI
     * @throws ManualLoginRequired
     * @throws WSApiException
     */
    public static function fromToken(object $session, ?callable $persist_session_fct = NULL, ?string $username = NULL) : WealthsimpleAPI {
        $ws = new WealthsimpleAPI($session);
        $ws->checkOAuthToken($persist_session_fct, $username);
        return $ws;
    }
}
