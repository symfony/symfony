<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Util\XliffUtils;
use Symfony\Component\Config\Resource\FileResource;

/**
 * XliffFileLoader loads translations from XLIFF files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class XliffFileLoader implements LoaderInterface
{
    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function load($resource, $locale, $domain = 'messages')
    {
        if (!stream_is_local($resource)) {
            throw new InvalidResourceException(sprintf('This is not a local file "%s".', $resource));
        }

        if (!file_exists($resource)) {
            throw new NotFoundResourceException(sprintf('File "%s" not found.', $resource));
        }

        list($xml, $encoding) = $this->parseFile($resource);
        $xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');

        $catalogue = new MessageCatalogue($locale);
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
        $catalogue->addResource(new FileResource($resource));

        return $catalogue;
    }

    /**
     * Convert a UTF8 string to the specified encoding.
     *
     * @param string $content  String to decode
     * @param string $encoding Target encoding
     *
     * @return string
     */
    private function utf8ToCharset($content, $encoding = null)
    {
        if ('UTF-8' !== $encoding && !empty($encoding)) {
            if (function_exists('mb_convert_encoding')) {
                return mb_convert_encoding($content, $encoding, 'UTF-8');
            }

            if (function_exists('iconv')) {
                return iconv('UTF-8', $encoding, $content);
            }

            throw new \RuntimeException('No suitable convert encoding function (use UTF-8 as your encoding or install the iconv or mbstring extension).');
        }

        return $content;
    }

    /**
     * Validates and parses the given file into an array that contains a SimpleXml instance and the encoding.
     *
     * @param string $file
     *
     * @return array
     *
     * @throws \RuntimeException
     * @throws InvalidResourceException
     */
    private function parseFile($file)
    {
        $dom = XliffUtils::loadFile($file);

        return array(simplexml_import_dom($dom), strtoupper($dom->encoding));
    }
}
