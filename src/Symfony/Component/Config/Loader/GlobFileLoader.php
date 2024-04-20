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
 * GlobFileLoader loads files from a glob pattern.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class GlobFileLoader extends FileLoader
{
    public function load(mixed $resource, ?string $type = null): mixed
    {
        return $this->import($resource);
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'glob' === $type;
    }
}
