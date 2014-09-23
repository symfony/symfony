<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Reader;

use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * Reads .php resource bundles.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class PhpBundleReader implements BundleReaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function read($path, $locale)
    {
        $fileName = $path.'/'.$locale.'.php';

        if (!file_exists($fileName)) {
            throw new ResourceBundleNotFoundException(sprintf(
                'The resource bundle "%s/%s.php" does not exist.',
                $path,
                $locale
            ));
        }

        if (!is_file($fileName)) {
            throw new RuntimeException(sprintf(
                'The resource bundle "%s/%s.php" is not a file.',
                $path,
                $locale
            ));
        }

        return include $fileName;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocales($path)
    {
        $locales = glob($path.'/*.php');

        // Remove file extension and sort
        array_walk($locales, function (&$locale) { $locale = basename($locale, '.php'); });
        sort($locales);

        return $locales;
    }
}
