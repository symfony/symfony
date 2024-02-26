<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

use Symfony\Component\VarExporter\LazyGhostTrait;

class LazyObject
{
    use LazyGhostTrait;

    public bool $readableProperty;

    public function __construct()
    {
        self::createLazyGhost(initializer: $this->hydrate(...), instance: $this);
    }

    private function hydrate(): void
    {
        $this->readableProperty = true;
    }
}
