<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Speaks cleartext HTTP/2 with prior knowledge only, like gRPC servers: it sends its SETTINGS frame on accept,
// closes connections that do not start with the HTTP/2 preface, and echoes the body of each request.

$port = (int) ($argv[1] ?? 8062);
$server = stream_socket_server('tcp://127.0.0.1:'.$port, $errno, $errstr);
$frame = static fn (int $type, int $flags, int $stream, string $payload = ''): string => substr(pack('N', strlen($payload)), 1).pack('CCN', $type, $flags, $stream).$payload;
$connections = [];

while (true) {
    $read = [$server, ...array_column($connections, 'socket')];
    $write = $except = null;

    if (!stream_select($read, $write, $except, 30)) {
        continue;
    }

    foreach ($read as $socket) {
        if ($server === $socket) {
            if ($socket = @stream_socket_accept($server, 0)) {
                stream_set_blocking($socket, false);
                fwrite($socket, $frame(0x4, 0, 0));
                $connections[(int) $socket] = ['socket' => $socket, 'buffer' => '', 'preface' => false, 'bodies' => []];
            }

            continue;
        }

        $c = &$connections[(int) $socket];
        $c['buffer'] .= $data = (string) fread($socket, 65536);

        if ('' === $data && feof($socket) || !$c['preface'] && 24 <= strlen($c['buffer']) && !str_starts_with($c['buffer'], "PRI * HTTP/2.0\r\n\r\nSM\r\n\r\n")) {
            fclose($socket);
            unset($c, $connections[(int) $socket]);

            continue;
        }

        if (!$c['preface'] && 24 <= strlen($c['buffer'])) {
            $c['buffer'] = substr($c['buffer'], 24);
            $c['preface'] = true;
        }

        while ($c['preface'] && 9 <= strlen($c['buffer']) && 9 + ($length = unpack('N', "\0".$c['buffer'])[1]) <= strlen($c['buffer'])) {
            ['type' => $type, 'flags' => $flags, 'stream' => $stream] = unpack('Ctype/Cflags/Nstream', $c['buffer'], 3);
            $payload = substr($c['buffer'], 9, $length);
            $c['buffer'] = substr($c['buffer'], 9 + $length);

            if (0x4 === $type && !($flags & 0x1)) {
                fwrite($socket, $frame(0x4, 0x1, 0));
            } elseif (0x0 === $type) {
                $c['bodies'][$stream] = ($c['bodies'][$stream] ?? '').$payload;
            }

            if (in_array($type, [0x0, 0x1], true) && $flags & 0x1) {
                // A HEADERS frame holding only ":status: 200" from the HPACK static table, then the echoed body
                fwrite($socket, $frame(0x1, 0x4, $stream, "\x88").$frame(0x0, 0x1, $stream, $c['bodies'][$stream] ?? ''));
                unset($c['bodies'][$stream]);
            }
        }

        unset($c);
    }
}
