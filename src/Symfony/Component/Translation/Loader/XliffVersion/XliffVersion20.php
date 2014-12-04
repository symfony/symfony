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
 * XliffVersion20 loads XLIFF files identified with version 2.0
 *
 * @author Berny Cantos <be@rny.cc>
 */
class XliffVersion20 extends AbstractXliffVersion
{
    /**
     * @return string
     */
    public function getSchema()
    {
        $source = file_get_contents(__DIR__.'/../schema/dic/xliff-core/xliff-core-2.0.xsd');

        return $this->fixXmlLocation($source, 'informativeCopiesOf3rdPartySchemas/w3c/xml.xsd');
    }

    /**
     * @param \DOMDocument $dom
     * @param MessageCatalogue $catalogue
     * @param string $domain
     */
    public function extract(\DOMDocument $dom, MessageCatalogue $catalogue, $domain)
    {
        $xml = simplexml_import_dom($dom);
        $encoding = strtoupper($dom->encoding);

        $xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:2.0');

        foreach ($xml->xpath('//xliff:unit/xliff:segment') as $segment) {
            $source = $segment->source;

            // If the xlf file has another encoding specified, try to convert it because
            // simple_xml will always return utf-8 encoded values
            $target = $this->utf8ToCharset((string) $segment->target, $encoding);

            $catalogue->set((string) $source, $target, $domain);
        }
    }
}
