<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/../vendor/autoload.php';

use PPFinances\Wealthsimple\WealthsimpleAPI;
use PPFinances\Wealthsimple\WealthsimpleAPIBase;
use PPFinances\Wealthsimple\Exceptions\CurlException;

$api = (new ReflectionClass(WealthsimpleAPI::class))->newInstanceWithoutConstructor();
$request = new ReflectionMethod(WealthsimpleAPIBase::class, 'sendHttpRequest');
$url = 'file://' . __FILE__;

if ($request->invoke($api, $url, 'GET') !== file_get_contents(__FILE__)) {
    throw new RuntimeException('The request must return its response body.');
}
$response = $request->invoke($api, $url, 'GET', NULL, [], FALSE, TRUE);
if ($response->body !== file_get_contents(__FILE__) || $response->cookies !== []) {
    throw new RuntimeException('The request must return its response body and cookies.');
}
try {
    $request->invoke($api, 'file:///nonexistent-ws-api-test-file', 'GET');
    throw new RuntimeException('The request must throw on a cURL error.');
} catch (CurlException $e) {
}

echo "CompatibilityTest passed\n";
