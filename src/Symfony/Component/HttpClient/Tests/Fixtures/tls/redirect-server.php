<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Serves both TLS and plain HTTP on one port: TLS requests are redirected to the
// plain URL of the same host and port, plain requests echo their headers as JSON.

$port = (int) ($argv[1] ?? 8059);
$context = stream_context_create(['ssl' => ['local_cert' => __DIR__.'/server.crt', 'local_pk' => __DIR__.'/server.key']]);
$server = stream_socket_server('tcp://127.0.0.1:'.$port, $errno, $errstr, \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN, $context);

while (true) {
    if (!$conn = @stream_socket_accept($server, 30)) {
        continue;
    }

    $tls = "\x16" === stream_socket_recvfrom($conn, 1, \STREAM_PEEK);

    if ($tls && !@stream_socket_enable_crypto($conn, true, \STREAM_CRYPTO_METHOD_TLS_SERVER)) {
        fclose($conn);
        continue;
    }

    $headers = [];
    while (false !== ($line = fgets($conn)) && '' !== ($line = trim($line))) {
        if (false !== $i = strpos($line, ':')) {
            $headers[strtolower(substr($line, 0, $i))] = trim(substr($line, $i + 1));
        }
    }

    if ($tls) {
        fwrite($conn, "HTTP/1.1 301 Moved Permanently\r\nLocation: http://127.0.0.1:$port/\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
    } else {
        $body = json_encode($headers);
        fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body);
    }

    fclose($conn);
}
