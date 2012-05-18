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
            throw new \InvalidArgumentException(sprintf('This is not a local file "%s".', $resource));
        }

        $xml = $this->parseFile($resource);
        $xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');

        $catalogue = new MessageCatalogue($locale);
        foreach ($xml->xpath('//xliff:trans-unit') as $translation) {
            $catalogue->set((string) $translation->source, (string) $translation->target, $domain);
        }
        $catalogue->addResource(new FileResource($resource));

        return $catalogue;
    }

    /**
     * Validates and parses the given file into a SimpleXMLElement
     *
     * @param string $file
     *
     * @return SimpleXMLElement
     */
    private function parseFile($file)
    {
        $dom = new \DOMDocument();
        $current = libxml_use_internal_errors(true);
        if (!@$dom->load($file, defined('LIBXML_COMPACT') ? LIBXML_COMPACT : 0)) {
            throw new \RuntimeException(implode("\n", $this->getXmlErrors()));
        }

        $location = str_replace('\\', '/', __DIR__).'/schema/dic/xliff-core/xml.xsd';
        $parts = explode('/', $location);
        if (0 === stripos($location, 'phar://')) {
            $tmpfile = tempnam(sys_get_temp_dir(), 'sf2');
            if ($tmpfile) {
                copy($location, $tmpfile);
                $parts = explode('/', str_replace('\\', '/', $tmpfile));
            }
        }
        $drive = '\\' === DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
        $location = 'file:///'.$drive.implode('/', array_map('rawurlencode', $parts));

        $source = file_get_contents(__DIR__.'/schema/dic/xliff-core/xliff-core-1.2-strict.xsd');
        $source = str_replace('http://www.w3.org/2001/xml.xsd', $location, $source);

        if (!@$dom->schemaValidateSource($source)) {
            throw new \RuntimeException(implode("\n", $this->getXmlErrors()));
        }
        $dom->validateOnParse = true;
        $dom->normalizeDocument();
        libxml_use_internal_errors($current);

        return simplexml_import_dom($dom);
    }

    /**
     * Returns the XML errors of the internal XML parser
     *
     * @return array  An array of errors
     */
    private function getXmlErrors()
    {
        $errors = array();
        foreach (libxml_get_errors() as $error) {
            $errors[] = sprintf('[%s %s] %s (in %s - line %d, column %d)',
                LIBXML_ERR_WARNING == $error->level ? 'WARNING' : 'ERROR',
                $error->code,
                trim($error->message),
                $error->file ? $error->file : 'n/a',
                $error->line,
                $error->column
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $errors;
    }
}
