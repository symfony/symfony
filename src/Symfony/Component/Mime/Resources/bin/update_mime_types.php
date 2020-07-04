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
$data = json_decode(file_get_contents('https://cdn.jsdelivr.net/gh/jshttp/mime-db@v1.44.0/db.json'), true);
$new = [];
foreach ($data as $mimeType => $mimeTypeInformation) {
    if (!array_key_exists('extensions', $mimeTypeInformation)) {
        continue;
    }
    $new[$mimeType] = $mimeTypeInformation['extensions'];
}

$xml = simplexml_load_string(file_get_contents('https://raw.github.com/minad/mimemagic/master/script/freedesktop.org.xml'));
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

// we merge the 2 maps (we never remove old mime types)
$map = array_replace_recursive($current, $new);
ksort($map);

// force an extension to be in the first position on the map
$forceExtensionInFirstPositionByMimeType = [
    'application/vnd.apple.keynote' => 'key',
    'audio/mpeg' => 'mp3',
    'text/markdown' => 'md',
    'text/x-markdown' => 'md',
];
foreach ($forceExtensionInFirstPositionByMimeType as $mimeType => $extensionToRemove) {
    $map[$mimeType] = array_unique(array_merge([$extensionToRemove], $map[$mimeType]));
}

$data = $pre;
foreach ($map as $mimeType => $exts) {
    $data .= sprintf("        '%s' => ['%s'],\n", $mimeType, implode("', '", array_unique($exts)));
}
$data .= $post;

// reverse map
// we prefill the extensions with some preferences for content-types
$exts = [
    'aif' => ['audio/x-aiff'],
    'aiff' => ['audio/x-aiff'],
    'aps' => ['application/postscript'],
    'avi' => ['video/avi'],
    'bmp' => ['image/bmp'],
    'bz2' => ['application/x-bz2'],
    'css' => ['text/css'],
    'csv' => ['text/csv'],
    'dmg' => ['application/x-apple-diskimage'],
    'doc' => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'eml' => ['message/rfc822'],
    'exe' => ['application/x-ms-dos-executable'],
    'flv' => ['video/x-flv'],
    'gif' => ['image/gif'],
    'gz' => ['application/x-gzip'],
    'hqx' => ['application/stuffit'],
    'htm' => ['text/html'],
    'html' => ['text/html'],
    'jar' => ['application/x-java-archive'],
    'jpeg' => ['image/jpeg'],
    'jpg' => ['image/jpeg'],
    'js' => ['text/javascript'],
    'm3u' => ['audio/x-mpegurl'],
    'm4a' => ['audio/mp4'],
    'mdb' => ['application/x-msaccess'],
    'mid' => ['audio/midi'],
    'midi' => ['audio/midi'],
    'mov' => ['video/quicktime'],
    'mp3' => ['audio/mpeg'],
    'mp4' => ['video/mp4'],
    'mpeg' => ['video/mpeg'],
    'mpg' => ['video/mpeg'],
    'ogg' => ['audio/ogg'],
    'pdf' => ['application/pdf'],
    'php' => ['application/x-php'],
    'php3' => ['application/x-php'],
    'php4' => ['application/x-php'],
    'php5' => ['application/x-php'],
    'png' => ['image/png'],
    'ppt' => ['application/vnd.ms-powerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'ps' => ['application/postscript'],
    'rar' => ['application/x-rar-compressed'],
    'rtf' => ['application/rtf'],
    'sit' => ['application/x-stuffit'],
    'svg' => ['image/svg+xml'],
    'tar' => ['application/x-tar'],
    'tif' => ['image/tiff'],
    'tiff' => ['image/tiff'],
    'ttf' => ['application/x-font-truetype'],
    'txt' => ['text/plain'],
    'vcf' => ['text/x-vcard'],
    'wav' => ['audio/wav'],
    'wma' => ['audio/x-ms-wma'],
    'wmv' => ['audio/x-ms-wmv'],
    'xls' => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'xml' => ['application/xml'],
    'zip' => ['application/zip'],
];
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
