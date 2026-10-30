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
        return json_encode(array_shift($responses));
    }
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
    print "Login tests passed\n";
}
