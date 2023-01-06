<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost;

class NoMagicClass
{
    public function __get($name)
    {
        throw new \BadMethodCallException(__FUNCTION__."({$name})");
    }

    public function __set($name, $value)
    {
        throw new \BadMethodCallException(__FUNCTION__."({$name})");
    }

    public function __isset($name): bool
    {
        throw new \BadMethodCallException(__FUNCTION__."({$name})");
    }

    public function __unset($name)
    {
        throw new \BadMethodCallException(__FUNCTION__."({$name})");
    }
}
