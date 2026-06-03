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

use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symfony\Component\Intl\Util\GzipStreamWrapper;

/**
 * Reads .php resource bundles.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class PhpBundleReader implements BundleReaderInterface
{
    public function read(string $path, string $locale): mixed
    {
        $fileName = $path.'/'.$locale.'.php';

        // prevent directory traversal attacks
        if (\dirname($fileName) !== $path) {
            throw new ResourceBundleNotFoundException(\sprintf('The resource bundle "%s" does not exist.', $fileName));
        }

        if (is_file($fileName.'.gz')) {
            return GzipStreamWrapper::require($fileName.'.gz');
        }

        if (!is_file($fileName)) {
            throw new ResourceBundleNotFoundException(\sprintf('The resource bundle "%s" does not exist.', $fileName));
        }

        return include $fileName;
    }
}
