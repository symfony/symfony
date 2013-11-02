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

/**
 * Base class for {@link BundleReaderInterface} implementations.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractBundleReader implements BundleReaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getLocales($path)
    {
        $extension = '.' . $this->getFileExtension();
        $locales = glob($path . '/*' . $extension);

        // Remove file extension and sort
        array_walk($locales, function (&$locale) use ($extension) { $locale = basename($locale, $extension); });
        sort($locales);

        return $locales;
    }

    /**
     * Returns the extension of locale files in this bundle.
     *
     * @return string The file extension (without leading dot).
     */
    abstract protected function getFileExtension();
}
