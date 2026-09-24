<?php

if ('cli-server' !== \PHP_SAPI) {
    // safe guard against unwanted execution
    throw new \Exception("You cannot run this script directly, it's a fixture for TestHttpServer.");
}

switch (parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH)) {
    default:
        exit;

    case '/matrix-error':
        http_response_code(500);
        // no break

    case '/matrix':
        // 4 MB of JSON, which gzip shrinks by about 600 times
        $body = json_encode(array_fill(0, 1500, array_fill(0, 1500, 0)));
        break;
}

header('Content-Type: application/json');
header('Content-Encoding: gzip');
echo gzencode($body);
