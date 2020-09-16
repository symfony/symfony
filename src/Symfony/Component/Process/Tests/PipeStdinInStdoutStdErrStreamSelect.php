<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

define('ERR_SELECT_FAILED', 1);
define('ERR_TIMEOUT', 2);
define('ERR_READ_FAILED', 3);
define('ERR_WRITE_FAILED', 4);

$read = [\STDIN];
$write = [\STDOUT, \STDERR];

stream_set_blocking(\STDIN, 0);
stream_set_blocking(\STDOUT, 0);
stream_set_blocking(\STDERR, 0);

$out = $err = '';
while ($read || $write) {
    $r = $read;
    $w = $write;
    $e = null;
    $n = stream_select($r, $w, $e, 5);

    if (false === $n) {
        exit(ERR_SELECT_FAILED);
    } elseif ($n < 1) {
        exit(ERR_TIMEOUT);
    }

    if (in_array(\STDOUT, $w) && strlen($out) > 0) {
        $written = fwrite(\STDOUT, (string) $out, 32768);
        if (false === $written) {
            exit(ERR_WRITE_FAILED);
        }
        $out = (string) substr($out, $written);
    }
    if (null === $read && '' === $out) {
        $write = array_diff($write, [\STDOUT]);
    }

    if (in_array(\STDERR, $w) && strlen($err) > 0) {
        $written = fwrite(\STDERR, (string) $err, 32768);
        if (false === $written) {
            exit(ERR_WRITE_FAILED);
        }
        $err = (string) substr($err, $written);
    }
    if (null === $read && '' === $err) {
        $write = array_diff($write, [\STDERR]);
    }

    if ($r) {
        $str = fread(\STDIN, 32768);
        if (false !== $str) {
            $out .= $str;
            $err .= $str;
        }
        if (false === $str || feof(\STDIN)) {
            $read = null;
            if (!feof(\STDIN)) {
                exit(ERR_READ_FAILED);
            }
        }
    }
}
