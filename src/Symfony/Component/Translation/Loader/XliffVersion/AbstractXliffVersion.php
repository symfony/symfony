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

use Symfony\Component\Translation\MessageCatalogue;

/**
 * Base Xliff version loader class.
 *
 * @author Berny Cantos <be@rny.cc>
 */
abstract class AbstractXliffVersion
{
    /**
     * Get validation schema source for this version
     *
     * @return string
     */
    abstract public function getSchema();

    /**
     * Extract messages and metadata from DOMDocument into a MessageCatalogue
     *
     * @param \DOMDocument     $dom       Source to extract messages and metadata
     * @param MessageCatalogue $catalogue Catalogue where we'll collect messages and metadata
     * @param string           $domain    The domain
     */
    abstract public function extract(\DOMDocument $dom, MessageCatalogue $catalogue, $domain);

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
