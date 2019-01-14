<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// load new map
$data = file_get_contents('https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types');
$new = [];
foreach (explode("\n", $data) as $line) {
    if (!$line || '#' == $line[0]) {
        continue;
    }
    $mimeType = substr($line, 0, strpos($line, "\t"));
    $extensions = explode(' ', substr($line, strrpos($line, "\t") + 1));
    $new[$mimeType] = $extensions;
}

// load current map
$data = file_get_contents($output = __DIR__.'/../../MimeTypes.php');
$current = [];
$pre = '';
$post = '';
foreach (explode("\n", $data) as $line) {
    if (!preg_match("{^        '([^']+/[^']+)' => \['(.+)'\],$}", $line, $matches)) {
        if (!$current) {
            $pre .= $line."\n";
        } else {
            $post .= $line."\n";
        }
        continue;
    }
    $current[$matches[1]] = explode("', '", $matches[2]);
}

// we merge the 2 maps (we never remove old mime types)
$map = array_replace_recursive($current, $new);
ksort($map);

$data = $pre;
foreach ($map as $mimeType => $exts) {
    $data .= sprintf("        '%s' => ['%s'],\n", $mimeType, implode("', '", $exts));
}
$data .= $post;

// reverse map
$exts = [];
foreach ($map as $mimeType => $extensions) {
    foreach ($extensions as $extension) {
        $exts[$extension][] = $mimeType;
    }
}
ksort($exts);

$updated = '';
$state = 0;
foreach (explode("\n", $data) as $line) {
    if (!preg_match("{^        '([^'/]+)' => \['(.+)'\],$}", $line, $matches)) {
        if (1 === $state) {
            $state = 2;
            foreach ($exts as $ext => $mimeTypes) {
                $updated .= sprintf("        '%s' => ['%s'],\n", $ext, implode("', '", $mimeTypes));
            }
        }
        $updated .= $line."\n";
        continue;
    }
    $state = 1;
}

file_put_contents($output, $updated);

echo "Done.\n";
