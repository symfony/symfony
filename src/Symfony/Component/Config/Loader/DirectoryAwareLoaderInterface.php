<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Loader;

/**
 * A loader that can be scoped to a given filesystem directory.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
interface DirectoryAwareLoaderInterface
{
    public function forDirectory(string $currentDirectory): static;
}
