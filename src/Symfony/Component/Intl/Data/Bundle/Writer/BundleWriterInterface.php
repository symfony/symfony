<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Bundle\Writer;

/**
 * Writes resource bundle files.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
interface BundleWriterInterface
{
    public function write(string $path, string $locale, mixed $data): void;
}
