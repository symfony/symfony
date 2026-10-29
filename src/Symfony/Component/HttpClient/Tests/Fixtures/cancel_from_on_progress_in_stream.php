<?php

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;

$client = new CurlHttpClient();
$cancel = false;
$response = $client->request('GET', 'http://localhost:8057/max-duration', ['on_progress' => static function (int $dlNow) use (&$response, &$cancel) {
    if ($cancel && $dlNow) {
        $cancel = false;
        $response->cancel();
    }
}]);

$response->getStatusCode();

// Let body data arrive, so that the cancel happens in the curl_multi_exec() call of CurlHttpClient::stream()
usleep(100000);
$cancel = true;

try {
    foreach ($client->stream($response) as $chunk) {
    }
} catch (TransportException) {
}

echo $response->getInfo('error');
