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

/**
 * Reads resource bundle files.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
interface BundleReaderInterface
{
    /**
     * @return mixed returns an array or {@link \ArrayAccess} instance for
     *               complex data, a scalar value otherwise
     */
    public function read(string $path, string $locale): mixed;
}
