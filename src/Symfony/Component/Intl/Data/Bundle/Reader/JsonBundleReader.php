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
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * Reads .json resource bundles.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class JsonBundleReader implements BundleReaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function read($path, $locale)
    {
        $fileName = $path.'/'.$locale.'.json';

        // prevent directory traversal attacks
        if (dirname($fileName) !== $path) {
            throw new ResourceBundleNotFoundException(sprintf('The resource bundle "%s" does not exist.', $fileName));
        }

        if (!file_exists($fileName)) {
            throw new ResourceBundleNotFoundException(sprintf(
                'The resource bundle "%s" does not exist.',
                $fileName
            ));
        }

        if (!is_file($fileName)) {
            throw new RuntimeException(sprintf(
                'The resource bundle "%s" is not a file.',
                $fileName
            ));
        }

        $data = json_decode(file_get_contents($fileName), true);

        if (null === $data) {
            throw new RuntimeException(sprintf(
                'The resource bundle "%s" contains invalid JSON: %s',
                $fileName,
                json_last_error_msg()
            ));
        }

        return $data;
    }
}
