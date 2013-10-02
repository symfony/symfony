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

use Symfony\Component\Intl\Exception\RuntimeException;
use Symfony\Component\Intl\Exception\NoSuchLocaleException;
use Symfony\Component\Intl\ResourceBundle\Util\ArrayAccessibleResourceBundle;

/**
 * Reads binary .res resource bundles.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BinaryBundleReader extends AbstractBundleReader implements BundleReaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function read($path, $locale)
    {
        // Point for future extension: Modify this class so that it works also
        // if the \ResourceBundle class is not available.
        $bundle = new \ResourceBundle($locale, $path);

        // The bundle is NULL if the path does not look like a resource bundle
        // (i.e. contain a bunch of *.res files)
        if (null === $bundle) {
            throw new RuntimeException(sprintf(
                'Could not load the resource bundle "%s/%s.res".',
                $path,
                $locale
            ));
        }

        // The error U_USING_DEFAULT_WARNING appears if the locale is not found,
        // no fallback can be used and the current default locale is used
        // instead.
        // Note that fallback to default is only working when a bundle contains
        // a root.res file.
        if (U_USING_DEFAULT_WARNING === $bundle->getErrorCode()) {
            throw new NoSuchLocaleException(sprintf(
                'Could not load the resource bundle "%s" for locale "%s".',
                $path,
                $locale
            ));
        }

        // Other possible errors are U_USING_FALLBACK_WARNING and U_ZERO_ERROR,
        // which are OK for us.

        return new ArrayAccessibleResourceBundle($bundle);
    }

    /**
     * {@inheritdoc}
     */
    protected function getFileExtension()
    {
        return 'res';
    }
}
