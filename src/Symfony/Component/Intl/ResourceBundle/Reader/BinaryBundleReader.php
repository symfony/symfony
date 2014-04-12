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
        try {
            $bundle = new \ResourceBundle($locale, $path);
        } catch (\Exception $e) {
            // HHVM compatibility: constructor throws on invalid resource
            $bundle = null;
        }

        if (null === $bundle) {
            throw new RuntimeException(sprintf(
                'Could not load the resource bundle "%s/%s.res".',
                $path,
                $locale
            ));
        }

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
