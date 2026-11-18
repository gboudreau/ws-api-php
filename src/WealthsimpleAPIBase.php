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
    private const PASSKEY_OPTIONS_URL = 'https://api.production.wealthsimple.com/v1/webauthn/authentication/options';
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

    private function sendHttpRequest(string $url, $method = 'POST', $data = NULL, array $headers = [], bool $return_headers = FALSE, bool $return_cookies = FALSE, bool $return_status = FALSE) {
        $ch = curl_init();

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data === NULL ? '' : json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }

        $is_oauth = strpos($url, static::OAUTH_BASE_URL) !== FALSE;
        if ($is_oauth || $url === self::PASSKEY_OPTIONS_URL || $url === 'https://api.production.wealthsimple.com/v1/login_challenge') {
            if (!empty($this->session->session_id)) {
                $headers[] = "x-ws-session-id: {$this->session->session_id}";
            }
        }
        $has_authorization = FALSE;
        foreach ($headers as $header) {
            if (stripos($header, 'Authorization:') === 0) {
                $has_authorization = TRUE;
                break;
            }
        }
        if (!empty($this->session->access_token) && !in_array($data->grant_type ?? NULL, ['refresh_token', 'client_credentials'], TRUE) && !$has_authorization) {
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
            unset($ch);
            if ($cookie_jar !== NULL) {
                @unlink($cookie_jar);
            }
            throw new CurlException("Error executing sendPOST($url); cURL error: $errno $err", $errno);
        }

        if ($return_cookies) {
            unset($ch);
            $cookies = self::parseCookieJar($cookie_jar);
            @unlink($cookie_jar);
            return (object) ['body' => $result, 'cookies' => $cookies];
        }

        if ($return_status) {
            return (object) ['body' => $result, 'status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE)];
        }

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
                // Ok, we'll just generate one ourselves!
                $data = random_bytes(16);
                $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
                $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
                $this->session->wssdi = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
            }
        }
        if (empty($this->session->client_id)) {
            if (!empty($app_js_url)) {
                $resp = $this->sendGET($app_js_url, [], FALSE, TRUE);
                foreach (explode("\n", str_replace("\r", "", $resp->body)) as $line) {
                    if (preg_match('/"production"[^}]*clientId:"([a-f0-9]+)"/i', $line, $re)) {
                        $this->session->client_id = $re[1];
                    }
                }
            }
            if (!$this->session->client_id) {
                error_log("Using hard-coded client_id, because app JS URL could not be loaded. This might cause an authentication failure.");
                $this->session->client_id = '4da53ac2b03225bed1550eba8e4611e086c7b905a3855e6ed12ea08c246758fa'; // Valid as of 2026-11-16 - update in the future as needed
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
                $first_error = $e->response->errors[0] ?? NULL;
                $code = $first_error->extensions->code ?? NULL;
                $is_not_authorized = $code === 'UNAUTHENTICATED'
                    || (isset($e->response->message) && rtrim($e->response->message, '.') === 'Not Authorized')
                    || (isset($first_error->message) && rtrim($first_error->message, '.') === 'Not Authorized');
                if (!$is_not_authorized) {
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
                $this->persistSession($persist_session_fct, $username);
                return;
            }
            // Failed to refresh access token
        }
        throw new ManualLoginRequired("Failed to use OAuth token. Manual login needed.");
    }

    public const SCOPE_READ_ONLY  = 'invest.read trade.read tax.read';
    public const SCOPE_READ_WRITE = 'invest.read trade.read tax.read invest.write trade.write tax.write';

    private function persistSession(?callable $callback, ?string $username) : void {
        if ($callback !== NULL) {
            $callback($this->session, $username);
        }
    }

    /**
     * Log in with an existing passkey using Wealthsimple's private web API.
     * The authenticator is given a WebAuthn options object and must return a decoded
     * assertion (object or associative array), or NULL to cancel.
     * The optional persistence callback receives the session object and the local
     * session name ($username), which is not sent to Wealthsimple.
     *
     * @throws LoginFailedException
     */
    public static function loginWithPasskey(?string $username, callable $get_assertion, ?callable $persist_session_fct = NULL, string $scope = self::SCOPE_READ_ONLY) : WSAPISession {
        try {
            $ws = new WealthsimpleAPI();
        } catch (CurlException | UnexpectedException $e) {
            throw new LoginFailedException('Could not initialize the Wealthsimple passkey session.', 0);
        }
        $token_url = static::OAUTH_BASE_URL . '/token';
        $post = static function (string $url, ?object $data, array $headers, string $failure) use ($ws) : object {
            try {
                $response = $ws->sendHttpRequest($url, 'POST', $data, $headers, return_status: TRUE);
            } catch (CurlException $e) {
                throw new LoginFailedException($failure, 0);
            }
            $body = json_decode($response->body);
            if (($body->error ?? NULL) === 'login_challenge_required') {
                throw new LoginFailedException('Wealthsimple requires additional approval; use the regular login flow.', 0);
            }
            if ($response->status < 200 || $response->status >= 300 || !is_object($body) || property_exists($body, 'error')) {
                throw new LoginFailedException($failure, 0);
            }
            return $body;
        };

        $client = $post($token_url, (object) [
            'grant_type' => 'client_credentials',
            'scope' => 'read',
            'client_id' => $ws->session->client_id,
        ], [], 'Could not obtain the Wealthsimple app token.');
        $app_token = $client->access_token ?? NULL;
        if (!is_string($app_token) || $app_token === '') {
            throw new LoginFailedException('Wealthsimple did not return an app token.', 0);
        }

        $headers = ['x-wealthsimple-client: @wealthsimple/wealthsimple'];
        $challenge = $post(self::PASSKEY_OPTIONS_URL, NULL, [...$headers, "Authorization: Bearer $app_token"],
            'Could not request a Wealthsimple passkey challenge.');
        $options = $challenge->options ?? NULL;
        $key = $challenge->challenge_key ?? NULL;
        if (!is_object($options) || !is_string($options->challenge ?? NULL) || $options->challenge === '' || !is_string($key) || $key === '') {
            throw new LoginFailedException('Wealthsimple returned invalid passkey options.', 0);
        }

        $assertion = $get_assertion($options);
        $credential = (array) $assertion;
        $proof = (array) ($credential['response'] ?? NULL);
        if (($credential['type'] ?? NULL) !== 'public-key') {
            throw new LoginFailedException('No valid passkey assertion was provided.', 0);
        }
        foreach ([$credential['id'] ?? NULL, $credential['rawId'] ?? NULL, $proof['authenticatorData'] ?? NULL,
            $proof['clientDataJSON'] ?? NULL, $proof['signature'] ?? NULL] as $field) {
            if (!is_string($field) || $field === '') {
                throw new LoginFailedException('No valid passkey assertion was provided.', 0);
            }
        }

        $tokens = $post($token_url, (object) [
            'grant_type' => 'webauthn',
            'challenge_key' => $key,
            'webauthn_credential' => $assertion,
            'skip_provision' => TRUE,
            'redirect_uri' => 'https://my.wealthsimple.com/app/login',
            'scope' => $scope,
            'client_id' => $ws->session->client_id,
        ], [...$headers, 'x-ws-profile: undefined'], 'Wealthsimple rejected the passkey exchange. Restart for a fresh challenge.');
        if (!is_string($tokens->token_type ?? NULL) || strcasecmp($tokens->token_type, 'Bearer') !== 0) {
            throw new LoginFailedException('The passkey exchange did not return a bearer token.', 0);
        }
        // OAuth may omit scope when it matches the request.
        if (property_exists($tokens, 'scope') && (!is_string($tokens->scope) || array_diff(
            preg_split('/\s+/', $tokens->scope, -1, PREG_SPLIT_NO_EMPTY),
            preg_split('/\s+/', $scope, -1, PREG_SPLIT_NO_EMPTY)
        ))) {
            throw new LoginFailedException('The passkey exchange returned unexpected scopes.', 0);
        }
        $access = $tokens->access_token ?? NULL;
        $refresh = $tokens->refresh_token ?? NULL;
        if (!is_string($access) || $access === '' || !is_string($refresh) || $refresh === '') {
            throw new LoginFailedException('The passkey exchange did not return reusable session tokens.', 0);
        }
        $ws->session->access_token = $access;
        $ws->session->refresh_token = $refresh;
        $ws->persistSession($persist_session_fct, $username);
        return $ws->session;
    }

    /** Prompt for a Chrome WebAuthn assertion without echoing it. Requires a CLI terminal with stty. */
    public static function passkeyChromeAssertion(object $options) : ?object {
        $terminal_error = 'Run passkey login in a terminal that can hide input.';
        if (PHP_SAPI !== 'cli' || !function_exists('exec') || !stream_isatty(STDIN)) {
            throw new LoginFailedException($terminal_error, 0);
        }
        $options_literal = json_encode(json_encode($options, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR);
        echo <<<JS
Run this snippet in Chrome DevTools Console on https://my.wealthsimple.com/app/login:

(async () => {
  if (location.origin !== 'https://my.wealthsimple.com') {
    throw new Error('Open the official Wealthsimple sign-in page first.');
  }
  const copyAssertion = copy;
  const publicKey = PublicKeyCredential.parseRequestOptionsFromJSON(JSON.parse($options_literal));
  const credential = await navigator.credentials.get({ publicKey });
  if (!credential) throw new Error('No passkey assertion.');
  copyAssertion(JSON.stringify(credential.toJSON()));
  console.info('Assertion copied. Paste it into your local terminal prompt now.');
})();

JS;

        $mode = exec('stty -g 2>/dev/null', $output, $status);
        if ($status !== 0 || !$mode) {
            throw new LoginFailedException($terminal_error, 0);
        }
        try {
            // Read past the terminal's canonical line limit and handle Ctrl-C as cancellation.
            exec('stty -echo -echonl -icanon -isig min 1 time 0 2>/dev/null', $output, $status);
            if ($status !== 0) {
                throw new LoginFailedException($terminal_error, 0);
            }
            fwrite(STDERR, "\nPaste the one-time assertion (input hidden), then hit [return]: ");
            $value = '';
            while (($char = fgetc(STDIN)) !== FALSE && $char !== "\n" && $char !== "\r") {
                if ($char === "\x03" || $char === "\x04") {
                    return NULL;
                }
                if ($char === "\x7f" || $char === "\x08") {
                    $value = substr($value, 0, -1);
                } else {
                    $value .= $char;
                }
            }
        } finally {
            exec('stty ' . escapeshellarg($mode) . ' 2>/dev/null');
            fwrite(STDERR, "\n");
        }
        if (trim($value) === '') {
            return NULL;
        }
        $assertion = json_decode($value);
        if (!is_object($assertion)) {
            throw new LoginFailedException('The pasted assertion was not a JSON object.', 0);
        }
        return $assertion;
    }

    /**
     * Login on the Wealthsimple API using the provided credentials.
     *
     * @param string        $username            Wealthsimple username (email address)
     * @param string        $password            Password
     * @param string|null   $otp_answer          2FA code, if required
     * @param callable|null $persist_session_fct Function to persist the session object, upon successful login. Will receive a two parameters: the session object, which you can JSON-encode to persist, and the username. Careful! This object contains sensitive tokens.
     * @param string        $scope               Scope to request; See WealthsimpleAPI::SCOPE_*; Default to SCOPE_READ_ONLY.
     * @param callable|null $approval_callback   Called when mobile approval is required.
     *
     * @return WSAPISession On success, the session object is returned. You should store this object in your database for future use.
     * @throws LoginFailedException
     * @throws OTPRequiredException
     */
    private function loginInternal(string $username, string $password, ?string $otp_answer = NULL, ?callable $persist_session_fct = NULL, string $scope = self::SCOPE_READ_ONLY, ?callable $approval_callback = NULL) : WSAPISession {
        $client_response = json_decode(
            $this->sendPOST(
                static::OAUTH_BASE_URL . '/token',
                (object) [
                    'grant_type' => 'client_credentials',
                    'scope' => 'read',
                    'client_id' => $this->session->client_id,
                ]
            )
        );
        if (empty($client_response->access_token)) {
            throw new LoginFailedException('Client credentials failed', 0, $client_response);
        }
        $client_token = $client_response->access_token;
        $data = (object) [
            'grant_type'     => 'password',
            'username'       => $username,
            'password'       => $password,
            'skip_provision' => 'true',
            'scope'          => $scope,
            'client_id'      => $this->session->client_id,
            'otp_claim'      => NULL,
        ];
        $headers = ["x-wealthsimple-client: @wealthsimple/wealthsimple", "x-ws-profile: undefined", "Authorization: Bearer $client_token"];
        if (!empty($otp_answer)) {
            $headers[] = "x-wealthsimple-otp: $otp_answer;remember=true";
        }
        $response = $this->sendPOST(static::OAUTH_BASE_URL . '/token', $data, $headers);
        $response = json_decode($response);

        if (($response->error ?? NULL) === 'login_challenge_required' && ($response->purpose ?? NULL) === 'login_approval' && !empty($response->login_challenge_id)) {
            if ($approval_callback) {
                $approval_callback();
            } else {
                trigger_error('Approve the login in the Wealthsimple mobile app.', E_USER_WARNING);
            }
            $challenge_header = "x-wealthsimple-login-challenge-id: {$response->login_challenge_id}";
            $deadline = hrtime(TRUE) / 1e9 + 120;
            while (TRUE) {
                $challenge = json_decode($this->sendGET('https://api.production.wealthsimple.com/v1/login_challenge', ["Authorization: Bearer $client_token", $challenge_header]));
                if (($challenge->status ?? NULL) === 'approved') {
                    break;
                }
                if (($challenge->status ?? NULL) !== 'pending') {
                    throw new LoginFailedException('Login approval failed', 0, $challenge);
                }
                $remaining = $deadline - hrtime(TRUE) / 1e9;
                if ($remaining <= 0) {
                    throw new LoginFailedException('Login approval timed out', 0, $challenge);
                }
                sleep(5);
            }
            $retry_headers = array_values(array_filter($headers, fn($header) => stripos($header, 'x-wealthsimple-otp:') !== 0));
            $retry_headers[] = $challenge_header;
            $response = json_decode($this->sendPOST(static::OAUTH_BASE_URL . '/token', $data, $retry_headers));
        }

        if (@$response->error === 'invalid_grant' && empty($otp_answer)) {
            throw new OTPRequiredException("2FA code required");
        }
        if (!empty($response->error)) {
            throw new LoginFailedException("Login failed", 0, $response);
        }

        $this->session->access_token = $response->access_token;
        $this->session->refresh_token = $response->refresh_token;

        $this->persistSession($persist_session_fct, $username);

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
     * @param callable|null $approval_callback   Called when mobile approval is required.
     *
     * @return void
     * @throws LoginFailedException
     * @throws OTPRequiredException
     */
    public static function login(string $username, string $password, ?string $otp_answer = NULL, ?callable $persist_session_fct = NULL, string $scope = self::SCOPE_READ_ONLY, ?callable $approval_callback = NULL) : WSAPISession {
        $ws = new WealthsimpleAPI();
        return $ws->loginInternal($username, $password, $otp_answer, $persist_session_fct, $scope, $approval_callback);
    }

    /**
     * Access the Wealthsimple API using a session returned by login() or loginWithPasskey().
     *
     * @param object        $session             Session object
     * @param callable|null $persist_session_fct Function to persist the session object, upon successful login. Will receive a two parameters: the session object, which you can JSON-encode to persist, and the username. Careful! This object contains sensitive tokens.
     * @param string|null   $username            Username or local session name passed to $persist_session_fct.
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
