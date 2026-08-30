<?php

if ('cli-server' !== \PHP_SAPI) {
    // safe guard against unwanted execution
    throw new \Exception("You cannot run this script directly, it's a fixture for TestHttpServer.");
}

switch (parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH)) {
    default:
        exit;

    case '/bomb':
        // 8 MB of zeros, which gzip shrinks by about 1000 times
        $body = str_repeat("\0", 8 * 1024 * 1024);
        break;

    case '/bomb-error':
        http_response_code(500);
        $body = str_repeat("\0", 8 * 1024 * 1024);
        break;

    case '/compressible':
        // close to 4 MB of near identical records, which gzip shrinks by about 35 times
        $rows = [];
        for ($i = 0; $i < 40000; ++$i) {
            $rows[] = ['id' => $i, 'status' => 'pending', 'message' => 'The operation is still running, please retry later.'];
        }
        $body = json_encode($rows);
        break;

    case '/padded':
        // 64 KB of spaces, which gzip shrinks by about 675 times
        $body = str_repeat(' ', 64 * 1024);
        break;
}

header('Content-Type: application/json');
header('Content-Encoding: gzip');
echo gzencode($body);
