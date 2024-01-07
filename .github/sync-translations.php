<?php

// This script should be run after adding a new message to translate.
// It will ensure that all messages in "*.en.xlf" files are propagated to all languages.
// The resulting diff should then be submitted as a PR on the lowest maintained branch,
// possibly after using GPT to translate all the targets it contains
// (state="needs-review-translation" should then be used on corresponding target tags.)

use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;

require __DIR__.'/../vendor/autoload.php';

function dumpXliff1(string $defaultLocale, MessageCatalogue $messages, string $domain)
{
    $dom = new \DOMDocument('1.0', 'utf-8');
    $dom->formatOutput = true;

    $xliff = $dom->appendChild($dom->createElement('xliff'));
    $xliff->setAttribute('version', '1.2');
    $xliff->setAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');

    $xliffFile = $xliff->appendChild($dom->createElement('file'));
    $xliffFile->setAttribute('source-language', str_replace('_', '-', $defaultLocale));
    $xliffFile->setAttribute('target-language', 'no' === $messages->getLocale() ? 'nb' : str_replace('_', '-', $messages->getLocale()));
    $xliffFile->setAttribute('datatype', 'plaintext');
    $xliffFile->setAttribute('original', 'file.ext');

    $xliffBody = $xliffFile->appendChild($dom->createElement('body'));
    foreach ($messages->all($domain) as $source => $target) {
        $translation = $dom->createElement('trans-unit');
        $metadata = $messages->getMetadata($source, $domain);

        $translation->setAttribute('id', $metadata['id']);

        $s = $translation->appendChild($dom->createElement('source'));
        $s->appendChild($dom->createTextNode($source));

        $text = 1 === preg_match('/[&<>]/', $target) ? $dom->createCDATASection($target) : $dom->createTextNode($target);

        $targetElement = $dom->createElement('target');

        if ('en' !== $messages->getLocale() && $target === $source && 'Error' !== $source) {
            $targetElement->setAttribute('state', 'needs-translation');
        }
        if (isset($metadata['target-attributes'])) {
            foreach ($metadata['target-attributes'] as $key => $value) {
                $targetElement->setAttribute($key, $value);
            }
        }

        $t = $translation->appendChild($targetElement);
        $t->appendChild($text);

        $xliffBody->appendChild($translation);
    }

    return preg_replace('/^ +/m', '$0$0', $dom->saveXML());
}


foreach (['Security/Core' => 'security', 'Form' => 'validators', 'Validator' => 'validators'] as $component => $domain) {
    $dir = __DIR__.'/../src/Symfony/Component/'.$component.'/Resources/translations';

    $enCatalogue = (new XliffFileLoader())->load($dir.'/'.$domain.'.en.xlf', 'en', $domain);
    $finder = new Finder();

    foreach ($finder->files()->in($dir)->name('*.xlf') as $file) {
        $locale = substr($file->getBasename(), 1 + strlen($domain), -4);

        $catalogue = (new XliffFileLoader())->load($file, $locale, $domain);
        $localeCatalogue = new MessageCatalogue($locale);

        foreach ($enCatalogue->all($domain) as $id => $translation) {
            $metadata = [];
            if ($catalogue->defines($id, $domain)) {
                $translation = $catalogue->get($id, $domain);
                $metadata = $catalogue->getMetadata($id, $domain);
            }
            $metadata['id'] = $enCatalogue->getMetadata($id, $domain)['id'];
            $localeCatalogue->set($id, $translation, $domain);
            $localeCatalogue->setMetadata($id, $metadata, $domain);
        }

        file_put_contents($file, dumpXliff1('en', $localeCatalogue, $domain));
    }
}
