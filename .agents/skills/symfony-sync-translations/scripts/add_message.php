#!/usr/bin/env php
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Insert one translation message into every locale of an xlf catalog.
 *
 * Adds a <trans-unit> with the given id and English source to each
 * *.xlf file in a catalog directory: the English catalog gets the
 * source as its target (no state), every other locale gets its
 * translation with state="needs-review-translation". Idempotent: a
 * locale that already has the id is left untouched.
 *
 * Usage:
 *   add_message.php --dir DIR --id ID --source TEXT --translations FILE
 *
 *   --dir           Catalog directory holding the <domain>.<locale>.xlf files
 *   --id            Numeric trans-unit id (must be free in every locale)
 *   --source        English source text, already XML-ready (escape & < >)
 *   --translations  JSON file mapping every non-en locale to its translated
 *                   target, e.g. {"fr": "...", "de": "..."}
 *
 * Exit codes: 0 success, 2 bad arguments, 3 locale/translation mismatch.
 */

if ('cli' !== \PHP_SAPI) {
    throw new Exception('This script must be run from the command line.');
}

error_reporting(\E_ALL);

set_error_handler(static function (int $type, string $msg, string $file, int $line): void {
    throw new ErrorException($msg, 0, $type, $file, $line);
});

const ANCHOR = '        </body>';

function fail(string $message, int $code = 1): never
{
    fwrite(\STDERR, $message."\n");
    exit($code);
}

function unit(string $id, string $source, string $target, ?string $state): string
{
    $attr = null !== $state ? ' state="'.$state.'"' : '';

    return '            <trans-unit id="'.$id.'">'."\n"
        .'                <source>'.$source.'</source>'."\n"
        .'                <target'.$attr.'>'.$target.'</target>'."\n"
        .'            </trans-unit>'."\n";
}

function localeOf(string $filename): string
{
    // <domain>.<locale>.xlf  ->  <locale>
    $parts = explode('.', $filename);

    return $parts[\count($parts) - 2];
}

function parseArgs(array $argv): array
{
    $options = ['dir' => null, 'id' => null, 'source' => null, 'translations' => null];
    $args = \array_slice($argv, 1);

    for ($i = 0; $i < \count($args); ++$i) {
        $arg = $args[$i];
        $key = null;
        $value = null;

        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$key, $value] = explode('=', substr($arg, 2), 2);
        } elseif (str_starts_with($arg, '--')) {
            $key = substr($arg, 2);
            $value = $args[++$i] ?? null;
        }

        if (null === $key || !\array_key_exists($key, $options) || null === $value) {
            fail('Error: invalid arguments', 2);
        }

        $options[$key] = $value;
    }

    foreach ($options as $name => $value) {
        if (null === $value) {
            fail('Error: missing required argument --'.$name, 2);
        }
    }

    return $options;
}

$opts = parseArgs($argv);

if (!is_dir($opts['dir'])) {
    fail('Error: --dir is not a directory: '.$opts['dir']);
}

$files = array_values(array_filter(scandir($opts['dir']), static fn ($f) => str_ends_with($f, '.xlf')));
sort($files);
if (!$files) {
    fail('Error: no .xlf files in '.$opts['dir']);
}

$json = file_get_contents($opts['translations']);
if (false === $json) {
    fail('Error: cannot read translations file: '.$opts['translations']);
}
$translations = json_decode($json, true);
if (!\is_array($translations)) {
    fail('Error: translations file is not a valid JSON object: '.$opts['translations']);
}

$locales = [];
foreach ($files as $f) {
    $locales[localeOf($f)] = true;
}
unset($locales['en']);
$nonEn = array_keys($locales);

$missing = array_values(array_diff($nonEn, array_keys($translations)));
$extra = array_values(array_diff(array_keys($translations), $nonEn));
sort($missing);
sort($extra);
if ($missing || $extra) {
    $msg = [];
    if ($missing) {
        $msg[] = 'missing translations for: '.implode(', ', $missing);
    }
    if ($extra) {
        $msg[] = 'unknown locales in translations file: '.implode(', ', $extra);
    }
    fail('Error: '.implode('; ', $msg), 3);
}

$idMarker = '<trans-unit id="'.$opts['id'].'">';
$added = [];
$skipped = [];
foreach ($files as $f) {
    $path = $opts['dir'].'/'.$f;
    $content = file_get_contents($path);
    $loc = localeOf($f);

    if (str_contains($content, $idMarker)) {
        $skipped[] = $loc;
        continue;
    }
    if (!str_contains($content, ANCHOR)) {
        fail("Error: anchor '</body>' not found in ".$path);
    }

    if ('en' === $loc) {
        $block = unit($opts['id'], $opts['source'], $opts['source'], null);
    } else {
        $block = unit($opts['id'], $opts['source'], $translations[$loc], 'needs-review-translation');
    }

    $pos = strpos($content, ANCHOR);
    $content = substr_replace($content, $block.ANCHOR, $pos, \strlen(ANCHOR));
    file_put_contents($path, $content);
    $added[] = $loc;
}

sort($added);
sort($skipped);
echo json_encode(['id' => $opts['id'], 'added' => $added, 'skipped' => $skipped])."\n";
