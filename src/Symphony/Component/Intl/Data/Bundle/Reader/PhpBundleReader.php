<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Intl\Data\Bundle\Reader;

use Symphony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symphony\Component\Intl\Exception\RuntimeException;

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

        // prevent directory traversal attacks
        if (dirname($fileName) !== $path) {
            throw new ResourceBundleNotFoundException(sprintf('The resource bundle "%s" does not exist.', $fileName));
        }

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
}
