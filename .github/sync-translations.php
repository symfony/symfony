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

function dumpXliff1(string $defaultLocale, MessageCatalogue $messages, string $domain, ?\DOMElement $header = null)
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

    if (null !== $header) {
        mergeDom($dom, $xliffFile, $header);
    }

    $xliffBody = $xliffFile->appendChild($dom->createElement('body'));
    foreach ($messages->all($domain) as $source => $target) {
        $translation = $dom->createElement('trans-unit');
        $metadata = $messages->getMetadata($source, $domain);

        $translation->setAttribute('id', $metadata['id']);
        if (isset($metadata['resname'])) {
            $translation->setAttribute('resname', $metadata['resname']);
        }

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

function mergeDom(\DOMDocument $dom, \DOMNode $tree, \DOMNode $input)
{
    $new = $dom->createElement($input->tagName);
    foreach ($input->attributes as $key => $value) {
        $new->setAttribute($key, $value);
    }
    $tree->appendChild($new);
    foreach ($input->childNodes as $child) {
        if ($child instanceof \DOMText) {
            $new->appendChild($dom->createTextNode(str_replace('  ', ' ', $child->textContent)));
        } elseif ($child instanceof \DOMNode) {
            mergeDom($dom, $new, $child);
        } else {
            // We just need to update our script to handle this node types
            throw new \LogicException('Unsupported node type: '.get_class($child));
        }
    }
}

foreach (['Security/Core' => 'security', 'Form' => 'validators', 'Validator' => 'validators'] as $component => $domain) {
    $dir = __DIR__.'/../src/Symfony/Component/'.$component.'/Resources/translations';

    $enCatalogue = (new XliffFileLoader())->load($dir.'/'.$domain.'.en.xlf', 'en', $domain);
    file_put_contents($dir.'/'.$domain.'.en.xlf', dumpXliff1('en', $enCatalogue, $domain));

    $finder = new Finder();

    foreach ($finder->files()->in($dir)->name('*.xlf') as $file) {
        $locale = substr($file->getBasename(), 1 + strlen($domain), -4);

        if ('en' === $locale) {
            continue;
        }

        $catalogue = (new XliffFileLoader())->load($file, $locale, $domain);
        $localeCatalogue = new MessageCatalogue($locale);

        foreach ($enCatalogue->all($domain) as $resname => $source) {
            $metadata = [];
            if ($catalogue->defines($resname, $domain)) {
                $translation = $catalogue->get($resname, $domain);
                $metadata = $catalogue->getMetadata($resname, $domain);
            } else {
                $translation = $source;
            }
            $metadata['id'] = $enCatalogue->getMetadata($resname, $domain)['id'];
            if ($resname !== $source) {
                $metadata['resname'] = $resname;
            }
            $localeCatalogue->set($source, $translation, $domain);
            $localeCatalogue->setMetadata($source, $metadata, $domain);
        }

        $inputDom = new \DOMDocument();
        $inputDom->loadXML(file_get_contents($file->getRealPath()));
        $header = null;
        if (1 === $inputDom->getElementsByTagName('header')->count()) {
            $header = $inputDom->getElementsByTagName('header')->item(0);
        }

        file_put_contents($file, dumpXliff1('en', $localeCatalogue, $domain, $header));
    }
}
