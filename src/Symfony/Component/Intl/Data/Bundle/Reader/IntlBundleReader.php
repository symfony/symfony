<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Bundle\Reader;

use Symfony\Component\Intl\Data\Util\ArrayAccessibleResourceBundle;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;

/**
 * Reads binary .res resource bundles.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class IntlBundleReader implements BundleReaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function read($path, $locale)
    {
        // Point for future extension: Modify this class so that it works also
        // if the \ResourceBundle class is not available.
        try {
            // Never enable fallback. We want to know if a bundle cannot be found
            $bundle = new \ResourceBundle($locale, $path, false);
        } catch (\Exception $e) {
            // HHVM compatibility: constructor throws on invalid resource
            $bundle = null;
        }

        // The bundle is NULL if the path does not look like a resource bundle
        // (i.e. contain a bunch of *.res files)
        if (null === $bundle) {
            throw new ResourceBundleNotFoundException(sprintf('The resource bundle "%s/%s.res" could not be found.', $path, $locale));
        }

        // Other possible errors are U_USING_FALLBACK_WARNING and U_ZERO_ERROR,
        // which are OK for us.
        return new ArrayAccessibleResourceBundle($bundle);
    }
}
