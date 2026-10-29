<?php

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;

$response = (new CurlHttpClient())->request('GET', 'http://localhost:8057/timeout-body', ['on_progress' => static function (int $dlNow) use (&$response) {
    if ($dlNow) {
        $response->cancel();
    }
}]);

// getContent() throws only when the response is canceled before its headers are processed, which depends on timing
try {
    $response->getContent();
} catch (TransportException) {
}

echo $response->getInfo('error');
