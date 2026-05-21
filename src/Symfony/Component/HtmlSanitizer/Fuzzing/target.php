<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/* @var PhpFuzzer\Config $config */

if (!extension_loaded('libxml')) {
    throw new RuntimeException('ext-libxml is required for this fuzz test.');
}
if (!extension_loaded('dom')) {
    throw new RuntimeException('ext-dom is required for this fuzz test.');
}

$autoloaded = false;
foreach ([__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../../../vendor/autoload.php'] as $autoloadFile) {
    if (is_file($autoloadFile)) {
        require $autoloadFile;
        $autoloaded = true;

        break;
    }
}

if (!$autoloaded) {
    throw new RuntimeException('Run composer install for src/Symfony/Component/HtmlSanitizer before fuzzing HtmlSanitizer.');
}

$config->setMaxLen(4096);
$config->addDictionary(__DIR__.'/xss.dict');

$sanitizer = new HtmlSanitizer(
    (new HtmlSanitizerConfig())
        ->allowStaticElements()
        ->allowElement('a', '*')
        ->allowElement('area', '*')
        ->allowElement('blockquote', '*')
        ->allowElement('button', '*')
        ->allowElement('del', '*')
        ->allowElement('form', '*')
        ->allowElement('img', '*')
        ->allowElement('input', '*')
        ->allowElement('ins', '*')
        ->allowElement('q', '*')
        ->allowElement('video', '*')
        ->allowRelativeLinks()
        ->allowRelativeMedias()
        ->withMaxInputLength(4096)
);

$config->setTarget(static function (string $input) use ($sanitizer): void {
    assertNoDangerousUrlAttribute($sanitizer->sanitize($input));
});

function assertNoDangerousUrlAttribute(string $html): void
{
    if ('' === $html) {
        return;
    }
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadHTML(
        '<!DOCTYPE html><html><body>'.$html.'</body></html>',
        \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return;
    }

    foreach (['href', 'src', 'lowsrc', 'background', 'ping', 'action', 'formaction', 'poster', 'cite'] as $attribute) {
        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof DOMElement || !$element->hasAttribute($attribute)) {
                continue;
            }

            $value = html_entity_decode($element->getAttribute($attribute), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            $normalized = strtolower((string) preg_replace('/[\x00-\x20\x7f]+/', '', $value));

            if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:')) {
                throw new Error(sprintf('Dangerous "%s" attribute survived sanitization: "%s".', $attribute, $value));
            }
        }
    }
}
