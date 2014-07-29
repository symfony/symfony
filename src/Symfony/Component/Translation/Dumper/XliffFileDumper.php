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
        $xliffFile->setAttribute('source-language', $messages->getLocale());
        $xliffFile->setAttribute('datatype', 'plaintext');
        $xliffFile->setAttribute('original', 'file.ext');

        $xliffBody = $xliffFile->appendChild($dom->createElement('body'));
        foreach ($messages->all($domain) as $source => $target) {
            $translation = $dom->createElement('trans-unit');

            $translation->setAttribute('id', md5($source));
            $translation->setAttribute('resname', $source);

            $s = $translation->appendChild($dom->createElement('source'));
            $s->appendChild($dom->createTextNode($source));

            $text = $this->needsCDATA($target)
                    ? $dom->createCDATASection($target)
                    : $dom->createTextNode($target);

            $t = $translation->appendChild($dom->createElement('target'));
            $t->appendChild($text);

            $xliffBody->appendChild($translation);
        }

        return $dom->saveXML();
    }

    /**
     * Returns whether or not the data require a CDATA section
     * in the XLIFF file.
     *
     * @param string $data The string data that may require a CDATA section
     * @return bool
     */
    private function needsCDATA($data)
    {
        if (false !== strpos($data, '&')) {
            return true;
        }

        if (false !== strpos($data, '<')) {
            return true;
        }

        if (false !== strpos($data, '>')) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getExtension()
    {
        return 'xlf';
    }
}
