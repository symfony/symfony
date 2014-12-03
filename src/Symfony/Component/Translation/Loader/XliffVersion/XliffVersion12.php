<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader\XliffVersion;

use DOMDocument;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * XliffVersion12 loads XLIFF files identified with version 1.2
 *
 * @author Berny Cantos <be@rny.cc>
 */
class XliffVersion12
{
    /**
     * Get validation schema source for this version
     *
     * @return string
     */
    public function getSchema()
    {
        $source = file_get_contents(__DIR__.'/../schema/dic/xliff-core/xliff-core-1.2-strict.xsd');

        return $this->fixXmlLocation($source, 'http://www.w3.org/2001/xml.xsd');
    }

    /**
     * Extract messages and metadata from DOMDocument into a MessageCatalogue
     *
     * @param DOMDocument      $dom       Source to extract messages and metadata
     * @param MessageCatalogue $catalogue Catalogue where we'll collect messages and metadata
     * @param string           $domain    The domain
     */
    public function extract(DOMDocument $dom, MessageCatalogue $catalogue, $domain)
    {
        $xml = simplexml_import_dom($dom);
        $encoding = strtoupper($dom->encoding);

        $xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');

        foreach ($xml->xpath('//xliff:trans-unit') as $translation) {
            $attributes = $translation->attributes();

            if (!(isset($attributes['resname']) || isset($translation->source)) || !isset($translation->target)) {
                continue;
            }

            $source = isset($attributes['resname']) && $attributes['resname'] ? $attributes['resname'] : $translation->source;
            // If the xlf file has another encoding specified, try to convert it because
            // simple_xml will always return utf-8 encoded values
            $target = $this->utf8ToCharset((string) $translation->target, $encoding);

            $catalogue->set((string) $source, $target, $domain);

            if (isset($translation->note)) {
                $notes = array();
                foreach ($translation->note as $xmlNote) {
                    $noteAttributes = $xmlNote->attributes();
                    $note = array('content' => $this->utf8ToCharset((string) $xmlNote, $encoding));
                    if (isset($noteAttributes['priority'])) {
                        $note['priority'] = (int) $noteAttributes['priority'];
                    }

                    if (isset($noteAttributes['from'])) {
                        $note['from'] = (string) $noteAttributes['from'];
                    }

                    $notes[] = $note;
                }

                $catalogue->setMetadata((string) $source, array('notes' => $notes), $domain);
            }
        }
    }

    /**
     * Internally changes the URI of a dependent xsd to be loaded locally
     *
     * @param string $schemaSource Current content of schema file
     * @param string $xmlUri       External URI of XML to convert to local
     *
     * @return string
     */
    protected function fixXmlLocation($schemaSource, $xmlUri)
    {
        $newPath = str_replace('\\', '/', __DIR__).'/../schema/dic/xliff-core/xml.xsd';
        $parts = explode('/', $newPath);
        if (0 === stripos($newPath, 'phar://')) {
            $tmpfile = tempnam(sys_get_temp_dir(), 'sf2');
            if ($tmpfile) {
                copy($newPath, $tmpfile);
                $parts = explode('/', str_replace('\\', '/', $tmpfile));
            }
        }
        $drive = '\\' === DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
        $newPath = 'file:///'.$drive.implode('/', array_map('rawurlencode', $parts));

        return str_replace($xmlUri, $newPath, $schemaSource);
    }

    /**
     * Convert a UTF8 string to the specified encoding
     *
     * @param string $content String to decode
     * @param string $encoding Target encoding
     *
     * @throws \RuntimeException
     * @return string
     */
    protected function utf8ToCharset($content, $encoding = null)
    {
        if (empty($encoding) || 'UTF-8' === $encoding) {
            return $content;
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($content, $encoding, 'UTF-8');
        }

        if (function_exists('iconv')) {
            return iconv('UTF-8', $encoding, $content);
        }

        throw new \RuntimeException(
            'No suitable convert encoding function (use UTF-8 as your encoding or install the iconv or mbstring extension).'
        );
    }
}
