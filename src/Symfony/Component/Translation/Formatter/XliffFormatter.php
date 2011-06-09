<?php

namespace Symfony\Component\Translation\Formatter;

class XliffFormatter implements FormatterInterface
{
    private $source;

    /**
     * @param string $source source-language attribute for the <file> element
     */
    public function __construct($source = 'en')
    {
        $this->source = $source;
    }

    public function format(array $messages)
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $xliff = $dom->appendChild($dom->createElement('xliff'));
        $xliff->setAttribute('version', '1.2');
        $xliff->setAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');
        $xliffFile = $xliff->appendChild($dom->createElement('file'));
        $xliffFile->setAttribute('source-language', $this->source);
        $xliffFile->setAttribute('datatype', 'plaintext');
        $xliffFile->setAttribute('original', 'file.ext');
        $xliffBody = $xliffFile->appendChild($dom->createElement('body'));
        $id = 1;
        foreach ($messages as $source => $target) {
            $trans = $dom->createElement('trans-unit');
            $trans->setAttribute('id', $id);
            $s = $trans->appendChild($dom->createElement('source'));
            $s->appendChild($dom->createTextNode($source));
            $t = $trans->appendChild($dom->createElement('target'));
            $t->appendChild($dom->createTextNode($target));
            $xliffBody->appendChild($trans);
            $id++;
        }

        return $dom->saveXML();
    }

}