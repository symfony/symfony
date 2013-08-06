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

use Symfony\Component\Intl\Exception\InvalidArgumentException;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * Reads .php resource bundles.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PhpBundleReader extends AbstractBundleReader implements BundleReaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function read($path, $locale)
    {
        if ('en' !== $locale) {
            throw new InvalidArgumentException('Only the locale "en" is supported.');
        }

        $fileName = $path . '/' . $locale . '.php';

        if (!file_exists($fileName)) {
            throw new RuntimeException(sprintf(
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
    protected function getFileExtension()
    {
        return 'php';
    }
}
