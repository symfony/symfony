<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Util;

use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Translation\Exception\InvalidResourceException;

/**
 * XliffUtils is a bunch of utility methods to Xliff operations.
 *
 * This class contains static methods only and is not meant to be instantiated.
 *
 * @author Marcos D. Sánchez <marcosdsanchez@gmail.com>
 */
class XliffUtils
{
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Loads a Xliff file.
     *
     * @param string               $file             A Xliff file path
     * @param string|callable|null $schemaOrCallable An XSD schema file path, a callable, or null to disable validation
     *
     * @return \DOMDocument
     *
     * @throws \InvalidArgumentException When loading of Xliff file returns error
     */
    public static function loadFile($file, $schemaOrCallable = null)
    {
        $location = str_replace('\\', '/', __DIR__).'/../Loader/schema/dic/xliff-core/xml.xsd';
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

        $source = file_get_contents(__DIR__.'/../Loader/schema/dic/xliff-core/xliff-core-1.2-strict.xsd');
        $source = str_replace('http://www.w3.org/2001/xml.xsd', $location, $source);

        if (null === $schemaOrCallable) {
            $schemaOrCallable = function (\DOMDocument $dom) use ($source) {
                return @$dom->schemaValidateSource($source);
            };
        }

        try {
            $dom = XmlUtils::loadFile($file, $schemaOrCallable);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidResourceException(sprintf('Unable to load "%s": %s', $file, $e->getMessage()), $e->getCode(), $e);
        }

        $dom->normalizeDocument();

        return $dom;
    }
}
