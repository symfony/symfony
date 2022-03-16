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
$data = json_decode(file_get_contents('https://cdn.jsdelivr.net/gh/jshttp/mime-db@v1.49.0/db.json'), true);
$new = [];
foreach ($data as $mimeType => $mimeTypeInformation) {
    if (!array_key_exists('extensions', $mimeTypeInformation)) {
        continue;
    }
    $new[$mimeType] = $mimeTypeInformation['extensions'];
}

$xml = simplexml_load_string(file_get_contents('https://gitlab.freedesktop.org/xdg/shared-mime-info/-/raw/master/data/freedesktop.org.xml.in'));
foreach ($xml as $node) {
    $exts = [];
    foreach ($node->glob as $glob) {
        $pattern = (string) $glob['pattern'];
        if ('*' != $pattern[0] || '.' != $pattern[1]) {
            continue;
        }

        $exts[] = substr($pattern, 2);
    }

    if (!$exts) {
        continue;
    }

    $mt = strtolower((string) $node['type']);
    $new[$mt] = array_merge($new[$mt] ?? [], $exts);
    foreach ($node->alias as $alias) {
        $mt = strtolower((string) $alias['type']);
        $new[$mt] = array_merge($new[$mt] ?? [], $exts);
    }
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

$data = $pre;

// reverse map
// we prefill the extensions with some preferences for content-types
$exts = [
    'asice' => ['application/vnd.etsi.asic-e+zip'],
    'bz2' => ['application/x-bz2'],
    'csv' => ['text/csv'],
    'ecma' => ['application/ecmascript'],
    'flv' => ['video/x-flv'],
    'gif' => ['image/gif'],
    'gz' => ['application/x-gzip'],
    'htm' => ['text/html'],
    'html' => ['text/html'],
    'jar' => ['application/x-java-archive'],
    'jpg' => ['image/jpeg'],
    'js' => ['text/javascript'],
    'keynote' => ['application/vnd.apple.keynote'],
    'key' => ['application/vnd.apple.keynote'],
    'm3u' => ['audio/x-mpegurl'],
    'm4a' => ['audio/mp4'],
    'md' => ['text/markdown', 'text/x-markdown'],
    'mdb' => ['application/x-msaccess'],
    'mid' => ['audio/midi'],
    'mov' => ['video/quicktime'],
    'mp3' => ['audio/mpeg'],
    'ogg' => ['audio/ogg'],
    'pdf' => ['application/pdf'],
    'php' => ['application/x-php'],
    'ppt' => ['application/vnd.ms-powerpoint'],
    'rar' => ['application/x-rar-compressed'],
    'hqx' => ['application/stuffit'],
    'sit' => ['application/x-stuffit', 'application/stuffit'],
    'svg' => ['image/svg+xml'],
    'tar' => ['application/x-tar'],
    'tif' => ['image/tiff'],
    'ttf' => ['application/x-font-truetype'],
    'vcf' => ['text/x-vcard'],
    'wav' => ['audio/wav'],
    'wma' => ['audio/x-ms-wma'],
    'wmv' => ['audio/x-ms-wmv'],
    'xls' => ['application/vnd.ms-excel'],
    'zip' => ['application/zip'],
];

// we merge the 2 maps (we never remove old mime types)
$map = array_replace_recursive($current, $new);

foreach ($exts as $ext => $types) {
    foreach ($types as $mt) {
        if (!isset($map[$mt])) {
            $map += [$mt => [$ext]];
        }
    }
}
ksort($map);

foreach ($map as $mimeType => $extensions) {
    foreach ($exts as $ext => $types) {
        if (in_array($mimeType, $types, true)) {
            array_unshift($extensions, $ext);
        }
    }
    $data .= sprintf("        '%s' => ['%s'],\n", $mimeType, implode("', '", array_unique($extensions)));
}
$data .= $post;

foreach ($map as $mimeType => $extensions) {
    foreach ($extensions as $extension) {
        if ('application/octet-stream' === $mimeType && 'bin' !== $extension) {
            continue;
        }

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
                $updated .= sprintf("        '%s' => ['%s'],\n", $ext, implode("', '", array_unique($mimeTypes)));
            }
        }
        $updated .= $line."\n";
        continue;
    }
    $state = 1;
}

$updated = preg_replace('{Updated from upstream on .+?\.}', 'Updated from upstream on '.date('Y-m-d'), $updated, -1);

file_put_contents($output, rtrim($updated, "\n")."\n");

echo "Done.\n";
