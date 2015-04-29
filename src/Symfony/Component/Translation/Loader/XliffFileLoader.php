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

use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
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

        try {
            $dom = XmlUtils::loadFile($resource);
            $version = $this->getVersion($dom);
        } catch (\InvalidArgumentException $e) {
            $message = sprintf('Unable to load "%s": %s', $resource, $e->getMessage());

            throw new InvalidResourceException($message, $e->getCode(), $e);
        }

        $this->validateSchema($dom, $version->getSchema());

        $catalogue = new MessageCatalogue($locale);
        $version->extract($dom, $catalogue, $domain);

        $catalogue->addResource(new FileResource($resource));

        return $catalogue;
    }

    /**
     * @param \DOMDocument $dom
     * @param string       $schema source of the schema
     *
     * @throws InvalidResourceException
     */
    private function validateSchema(\DOMDocument $dom, $schema)
    {
        $internalErrors = libxml_use_internal_errors(true);

        if (!@$dom->schemaValidateSource($schema)) {
            throw new InvalidResourceException(implode("\n", $this->getXmlErrors($internalErrors)));
        }

        $dom->normalizeDocument();

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
    }

    /**
     * Returns the XML errors of the internal XML parser.
     *
     * @param bool $internalErrors
     *
     * @return array An array of errors
     */
    private function getXmlErrors($internalErrors)
    {
        $errors = array();
        foreach (libxml_get_errors() as $error) {
            $errors[] = sprintf('[%s %s] %s (in %s - line %d, column %d)',
                LIBXML_ERR_WARNING == $error->level ? 'WARNING' : 'ERROR',
                $error->code,
                trim($error->message),
                $error->file ?: 'n/a',
                $error->line,
                $error->column
            );
        }

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $errors;
    }

    /**
     * Detects xliff version from file.
     *
     * @param \DOMDocument $dom
     *
     * @throws \InvalidArgumentException
     *
     * @return XliffVersion\AbstractXliffVersion
     */
    private function getVersion(\DOMDocument $dom)
    {
        $versionNumber = $this->getVersionNumber($dom);

        switch ($versionNumber) {
            case '1.2':
                return new XliffVersion\XliffVersion12();

            case '2.0':
                return new XliffVersion\XliffVersion20();
        }

        throw new \InvalidArgumentException(sprintf(
            'No support implemented for loading XLIFF version "%s".',
            $versionNumber
        ));
    }

    /**
     * Gets xliff file version based on the root "version" attribute.
     * Defaults to 1.2 for backwards compatibility
     *
     * @param \DOMDocument $dom
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    private function getVersionNumber(\DOMDocument $dom)
    {
        /** @var \DOMNode $xliff */
        foreach ($dom->getElementsByTagName('xliff') as $xliff) {
            $version = $xliff->attributes->getNamedItem('version');
            if ($version) {
                return $version->nodeValue;
            }

            $namespace = $xliff->attributes->getNamedItem('xmlns');
            if ($namespace) {
                if (substr_compare('urn:oasis:names:tc:xliff:document:', $namespace->nodeValue, 0, 34) !== 0) {
                    throw new \InvalidArgumentException(sprintf(
                        'Not a valid XLIFF namespace "%s"',
                        $namespace
                    ));
                }

                return substr($namespace, 34);
            }
        }

        // Falls back to v1.2
        return '1.2';
    }
}
