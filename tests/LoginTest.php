<?php

namespace PPFinances\Wealthsimple {
    $responses = [];
    $requests = [];
    $clock = 0;
    function curl_init() { return (object) ['options' => []]; }
    function curl_setopt($ch, $option, $value) { $ch->options[$option] = $value; return TRUE; }
    function curl_exec($ch) {
        global $responses, $requests;
        $requests[] = $ch->options;
        $response = array_shift($responses);
        $ch->status = 200;
        if (is_array($response) && isset($response['_status'])) {
            $ch->status = $response['_status'];
            unset($response['_status']);
        }
        return is_string($response) ? $response : json_encode($response);
    }
    function curl_getinfo($ch, $option) { return $ch->status; }
    function curl_close($ch) {}
    function hrtime($as_number = FALSE) { global $clock; return $clock * 1000000000; }
    function sleep($seconds) { global $clock; $clock += $seconds; return 0; }
}

namespace {
    require_once __DIR__ . '/../vendor/autoload.php';

    use PPFinances\Wealthsimple\WealthsimpleAPI;
    use PPFinances\Wealthsimple\Sessions\WSAPISession;
    use PPFinances\Wealthsimple\Exceptions\LoginFailedException;
    use PPFinances\Wealthsimple\Exceptions\OTPRequiredException;

    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    function runLogin($responses, $otp = NULL, $callback = NULL, $persist = NULL) {
        $GLOBALS['responses'] = $responses;
        $GLOBALS['requests'] = [];
        $GLOBALS['clock'] = 0;
        $api = (new \ReflectionClass(WealthsimpleAPI::class))->newInstanceWithoutConstructor();
        $session = new WSAPISession();
        $session->client_id = 'client'; $session->session_id = 'session'; $session->wssdi = 'device'; $session->access_token = 'old-token';
        $property = new \ReflectionProperty(\PPFinances\Wealthsimple\WealthsimpleAPIBase::class, 'session');
        $property->setValue($api, $session);
        $method = new \ReflectionMethod(\PPFinances\Wealthsimple\WealthsimpleAPIBase::class, 'loginInternal');
        return $method->invoke($api, 'user', 'pass', $otp, $persist, WealthsimpleAPI::SCOPE_READ_ONLY, $callback);
    }

    $called = 0; $persisted = 0;
    $session = runLogin([
        ['access_token' => 'client-token'],
        ['error' => 'login_challenge_required', 'purpose' => 'login_approval', 'login_challenge_id' => 'challenge'],
        ['status' => 'pending'], ['status' => 'approved'],
        ['access_token' => 'user-token', 'refresh_token' => 'refresh'],
    ], '123456', function () use (&$called) { $called++; }, function () use (&$persisted) { $persisted++; });
    $requests = $GLOBALS['requests'];
    check(count($requests) === 5 && $called === 1 && $persisted === 1, 'Approval must poll, retry and persist once.');
    check($session->access_token === 'user-token', 'Login must return the user token.');
    check(json_decode($requests[0][CURLOPT_POSTFIELDS])->grant_type === 'client_credentials', 'First request must use client credentials.');
    check(!in_array('Authorization: Bearer old-token', $requests[0][CURLOPT_HTTPHEADER], TRUE), 'Client credentials must not use a stale user token.');
    foreach (array_slice($requests, 1) as $request) {
        check(in_array('Authorization: Bearer client-token', $request[CURLOPT_HTTPHEADER], TRUE), 'Requests must use the client token.');
        check(in_array('x-ws-session-id: session', $request[CURLOPT_HTTPHEADER], TRUE), 'Requests must preserve the session ID.');
        check(in_array('x-ws-device-id: device', $request[CURLOPT_HTTPHEADER], TRUE), 'Requests must preserve the device ID.');
    }
    check(in_array('x-wealthsimple-otp: 123456;remember=true', $requests[1][CURLOPT_HTTPHEADER], TRUE), 'Initial login must include OTP.');
    check(in_array('x-wealthsimple-login-challenge-id: challenge', $requests[2][CURLOPT_HTTPHEADER], TRUE), 'Poll must include challenge ID.');
    check(in_array('x-wealthsimple-login-challenge-id: challenge', $requests[4][CURLOPT_HTTPHEADER], TRUE), 'Retry must include challenge ID.');
    check(!in_array('x-wealthsimple-otp: 123456;remember=true', $requests[4][CURLOPT_HTTPHEADER], TRUE), 'Retry must omit OTP.');
    check($requests[1][CURLOPT_POSTFIELDS] === $requests[4][CURLOPT_POSTFIELDS], 'Retry must preserve login payload.');

    foreach (['rejected', 'expired', 'unexpected'] as $status) {
        try { runLogin([['access_token' => 'client'], ['error' => 'login_challenge_required', 'purpose' => 'login_approval', 'login_challenge_id' => 'challenge'], ['status' => $status]], NULL, function () {}); throw new \RuntimeException('Expected approval failure'); }
        catch (LoginFailedException $e) { check(strpos($e->getMessage(), 'approval failed') !== FALSE, 'Terminal status must fail.'); }
    }
    try { runLogin([['error' => 'invalid_client']]); throw new \RuntimeException('Expected client failure'); }
    catch (LoginFailedException $e) { check(count($GLOBALS['requests']) === 1, 'Client failure must stop login.'); }
    try { runLogin([['access_token' => 'client'], ['error' => 'invalid_grant']]); throw new \RuntimeException('Expected OTP'); }
    catch (OTPRequiredException $e) {}
    $pending = array_fill(0, 25, ['status' => 'pending']);
    try { runLogin(array_merge([['access_token' => 'client'], ['error' => 'login_challenge_required', 'purpose' => 'login_approval', 'login_challenge_id' => 'challenge']], $pending), NULL, function () {}); throw new \RuntimeException('Expected timeout'); }
    catch (LoginFailedException $e) { check(strpos($e->getMessage(), 'timed out') !== FALSE, 'Pending approval must time out.'); }

    $options_url = 'https://api.production.wealthsimple.com/v1/webauthn/authentication/options';
    $options = (object) ['challenge' => 'challenge-data', 'rpId' => 'wealthsimple.com'];
    $assertion = [
        'type' => 'public-key', 'id' => 'credential-id', 'rawId' => 'credential-id',
        'response' => ['clientDataJSON' => 'data', 'authenticatorData' => 'data', 'signature' => 'sig'],
    ];
    $passkey_responses = [
        "Set-Cookie: wssdi=abcdef;\n<script src=\"https://my.wealthsimple.com/app-abc.js\"></script>",
        '"production":{clientId:"abcd"}',
        ['access_token' => 'app-token'],
        ['challenge_key' => 'challenge-key', 'options' => $options],
    ];
    $tokens = ['access_token' => 'user-token', 'refresh_token' => 'refresh', 'token_type' => 'bearer'];
    $authenticate = function ($received) use ($options, $assertion) {
        check($received == $options, 'The authenticator must receive the server options.');
        return $assertion;
    };
    $responses = [...$passkey_responses, $tokens, ['data' => ['securitySearch' => ['results' => []]]]];
    $requests = $saved = [];
    check(is_callable([WealthsimpleAPI::class, 'loginWithPasskey']), 'Passkey login must be available.');
    $session = WealthsimpleAPI::loginWithPasskey('personal', $authenticate, function ($session, $name) use (&$saved) {
        $saved[] = [$session, $name];
    });
    check($saved === [[$session, 'personal']], 'Persistence must use the PHP session object and unchanged local name.');
    check($session->access_token === 'user-token' && $session->refresh_token === 'refresh', 'Login must return reusable tokens.');
    WealthsimpleAPI::fromToken(json_decode(json_encode($session)));
    [, , $app, $challenge, $exchange, $verify] = $requests;
    check(json_decode($app[CURLOPT_POSTFIELDS], TRUE) === [
        'grant_type' => 'client_credentials', 'scope' => 'read', 'client_id' => 'abcd',
    ], 'App token must request only the read scope.');
    check($challenge[CURLOPT_URL] === $options_url && $challenge[CURLOPT_POST] === TRUE
        && $challenge[CURLOPT_POSTFIELDS] === '', 'Options must use an empty POST.');
    check(in_array('Authorization: Bearer app-token', $challenge[CURLOPT_HTTPHEADER], TRUE), 'Options must use the app token.');
    check($exchange[CURLOPT_URL] === $app[CURLOPT_URL], 'Exchange must use the OAuth token endpoint.');
    check(json_decode($exchange[CURLOPT_POSTFIELDS], TRUE) == [
        'grant_type' => 'webauthn', 'challenge_key' => 'challenge-key', 'webauthn_credential' => $assertion,
        'skip_provision' => TRUE, 'redirect_uri' => 'https://my.wealthsimple.com/app/login',
        'scope' => WealthsimpleAPI::SCOPE_READ_ONLY, 'client_id' => 'abcd',
    ] && json_decode($exchange[CURLOPT_POSTFIELDS])->skip_provision === TRUE, 'Exchange must send the assertion with the default scope and no session name.');
    check(!array_filter($exchange[CURLOPT_HTTPHEADER], fn($header) => stripos($header, 'Authorization:') === 0), 'Exchange must not send authorization.');
    foreach ([$app, $challenge, $exchange] as $request) {
        check(in_array("x-ws-session-id: {$session->session_id}", $request[CURLOPT_HTTPHEADER], TRUE), 'Requests must share a session ID.');
        check(in_array('x-ws-device-id: abcdef', $request[CURLOPT_HTTPHEADER], TRUE), 'Requests must share a device ID.');
    }
    check(in_array('Authorization: Bearer user-token', $verify[CURLOPT_HTTPHEADER], TRUE), 'Restored session must use the user token.');

    foreach ([NULL, [], ['type' => 'public-key', 'response' => []]] as $invalid) {
        $responses = $passkey_responses;
        $requests = [];
        try { WealthsimpleAPI::loginWithPasskey(NULL, fn() => $invalid); throw new \RuntimeException('Expected invalid assertion'); }
        catch (LoginFailedException $e) { check(count($requests) === 4 && str_contains($e->getMessage(), 'assertion'), 'Invalid assertions must not be exchanged.'); }
    }

    $responses = [...$passkey_responses, $tokens + ['scope' => 'invest.write']];
    $requests = [];
    WealthsimpleAPI::loginWithPasskey(NULL, $authenticate, scope: 'invest.write');
    check(json_decode($requests[4][CURLOPT_POSTFIELDS])->scope === 'invest.write', 'Exchange must send the requested custom scope.');

    foreach ([
        ['token_type' => 'DPoP'] + $tokens,
        ['token_type' => 'Bearer', 'access_token' => 'secret'],
        $tokens + ['scope' => 'invest.write trade.write'],
        $tokens + ['scope' => NULL],
        $tokens + ['_status' => 403],
        $tokens + ['error' => NULL],
        ['error' => 'login_challenge_required', 'extra' => 'secret'],
        '<html>secret</html>',
    ] as $invalid) {
        $responses = [...$passkey_responses, $invalid];
        $requests = [];
        try {
            WealthsimpleAPI::loginWithPasskey(NULL, $authenticate, fn() => throw new \RuntimeException('Unexpected persistence'), 'invest.write');
            throw new \RuntimeException('Expected exchange failure');
        } catch (LoginFailedException $e) {
            check(count($requests) === 5 && $e->response === NULL && !str_contains($e->getMessage(), 'secret'), 'Rejected exchanges must not retry or expose response bodies.');
        }
    }

    print "Login tests passed\n";
}
