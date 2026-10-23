<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$path = parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);

if (!in_array($path, ['/body', '/chunked'], true)) {
    echo 'Success';

    return;
}

header('Content-Type: application/octet-stream');
header('/chunked' === $path ? 'Transfer-Encoding: chunked' : 'Content-Length: 4194304');

for ($i = 0; $i < 256; ++$i) {
    $body = str_repeat(chr($i), 16384);
    echo '/chunked' === $path ? "4000\r\n".$body."\r\n" : $body;
    @ob_flush();
    flush();
}

if ('/chunked' === $path) {
    echo "0\r\n\r\n";
}
