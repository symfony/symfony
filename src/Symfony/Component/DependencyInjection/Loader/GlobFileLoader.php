<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

/**
 * GlobFileLoader loads files from a glob pattern.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class GlobFileLoader extends FileLoader
{
    public function load(mixed $resource, ?string $type = null): mixed
    {
        foreach ($this->glob($resource, false, $globResource) as $path => $info) {
            $this->import($path);
        }

        $this->container->addResource($globResource);

        return null;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'glob' === $type;
    }
}
