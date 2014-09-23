<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Icu;

use Symfony\Component\Intl\ResourceBundle\Reader\PhpBundleReader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IcuData
{
    /**
     * Returns the version of the bundled ICU data.
     *
     * @return string The version string.
     */
    public static function getVersion()
    {
        return trim(file_get_contents(__DIR__.'/../Resources/data/version.txt'));
    }

    /**
     * Returns whether the ICU data is stubbed.
     *
     * @return Boolean Returns true if the ICU data is stubbed, false if it is
     *         loaded from ICU .res files.
     */
    public static function isStubbed()
    {
        return true;
    }

    /**
     * Returns the path to the directory where the resource bundles are stored.
     *
     * @return string The absolute path to the resource directory.
     */
    public static function getResourceDirectory()
    {
        return realpath(__DIR__.'/../Resources/data');
    }

    /**
     * Returns a reader for reading resource bundles in this component.
     *
     * @return \Symfony\Component\Intl\ResourceBundle\Reader\BundleReaderInterface
     */
    public static function getBundleReader()
    {
        return new PhpBundleReader();
    }

    /**
     * This class must not be instantiated.
     */
    private function __construct()
    {
    }
}
