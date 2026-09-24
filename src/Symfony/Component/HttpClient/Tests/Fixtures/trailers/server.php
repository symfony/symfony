<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Answers with chunked bodies followed by trailer fields, which PHP's built-in server cannot send.

$port = (int) ($argv[1] ?? 8061);
$server = stream_socket_server('tcp://127.0.0.1:'.$port, $errno, $errstr);

while (true) {
    if (!$conn = @stream_socket_accept($server, 30)) {
        continue;
    }

    $path = explode(' ', (string) fgets($conn))[1] ?? '/';
    while (false !== ($line = fgets($conn)) && '' !== trim($line)) {
    }

    $chunked = "Transfer-Encoding: chunked\r\nConnection: close\r\n\r\n5\r\nhello\r\n0\r\n";
    fwrite($conn, match ($path) {
        '/trailers' => "HTTP/1.1 200 OK\r\nTrailer: grpc-status, x-repeat\r\n$chunked"."grpc-status: 0\r\nx-repeat: a\r\nX-Repeat: b\r\n\r\n",
        '/trailers-500' => "HTTP/1.1 500 Internal Server Error\r\n$chunked"."grpc-status: 13\r\n\r\n",
        '/no-trailers' => "HTTP/1.1 200 OK\r\n$chunked\r\n",
        '/content-length' => "HTTP/1.1 200 OK\r\nContent-Length: 5\r\nConnection: close\r\n\r\nhello",
        '/redirect' => "HTTP/1.1 302 Found\r\nLocation: /trailers\r\n$chunked"."x-redirect: 1\r\n\r\n",
        '/broken' => "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\nConnection: close\r\n\r\n5\r\nhel",
        '/unterminated-trailers' => "HTTP/1.1 200 OK\r\n$chunked"."grpc-status: 0\r\n",
        '/truncated-trailers' => "HTTP/1.1 200 OK\r\n$chunked"."grpc-status: 0\r\ngrpc-mess",
        '/oversized-trailers' => "HTTP/1.1 200 OK\r\n$chunked"."grpc-status: 0\r\nx-big: ".str_repeat('a', 20000)."\r\n\r\n",
        default => "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\nConnection: close\r\n\r\n",
    });
    fclose($conn);
}
