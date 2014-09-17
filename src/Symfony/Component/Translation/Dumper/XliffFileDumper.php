<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Dumper;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * XliffFileDumper generates xliff files from a message catalogue.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
class XliffFileDumper extends FileDumper
{
    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * {@inheritdoc}
     */
    public function dump(MessageCatalogue $messages, $options = array())
    {
        if (array_key_exists('default_locale', $options)) {
            $this->defaultLocale = $options['default_locale'];
        } else {
            $this->defaultLocale = \Locale::getDefault();
        }

        parent::dump($messages, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function format(MessageCatalogue $messages, $domain)
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;

        $xliff = $dom->appendChild($dom->createElement('xliff'));
        $xliff->setAttribute('version', '1.2');
        $xliff->setAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');

        $xliffFile = $xliff->appendChild($dom->createElement('file'));
        $xliffFile->setAttribute('source-language', str_replace('_', '-', $this->defaultLocale));
        $xliffFile->setAttribute('target-language', str_replace('_', '-', $messages->getLocale()));
        $xliffFile->setAttribute('datatype', 'plaintext');
        $xliffFile->setAttribute('original', 'file.ext');

        $xliffBody = $xliffFile->appendChild($dom->createElement('body'));
        foreach ($messages->all($domain) as $source => $target) {
            $translation = $dom->createElement('trans-unit');

            $translation->setAttribute('id', md5($source));
            $translation->setAttribute('resname', $source);

            $s = $translation->appendChild($dom->createElement('source'));
            $s->appendChild($dom->createTextNode($source));

            // Does the target contain characters requiring a CDATA section?
            $text = 1 === preg_match('/[&<>]/', $target) ? $dom->createCDATASection($target) : $dom->createTextNode($target);

            $t = $translation->appendChild($dom->createElement('target'));
            $t->appendChild($text);

            $metadata = $messages->getMetadata($source, $domain);
            if (null !== $metadata && array_key_exists('notes', $metadata) && is_array($metadata['notes'])) {
                foreach ($metadata['notes'] as $note) {
                    if (!isset($note['content'])) {
                        continue;
                    }

                    $n = $translation->appendChild($dom->createElement('note'));
                    $n->appendChild($dom->createTextNode($note['content']));

                    if (isset($note['priority'])) {
                        $n->setAttribute('priority', $note['priority']);
                    }

                    if (isset($note['from'])) {
                        $n->setAttribute('from', $note['from']);
                    }
                }
            }

            $xliffBody->appendChild($translation);
        }

        return $dom->saveXML();
    }

    /**
     * {@inheritdoc}
     */
    protected function getExtension()
    {
        return 'xlf';
    }
}
