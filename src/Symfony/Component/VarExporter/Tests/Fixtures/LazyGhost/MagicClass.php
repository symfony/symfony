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

class MagicClass
{
    public static int $destructCounter = 0;
    public int $cloneCounter = 0;
    private array $data = [];

    public function __construct()
    {
        $this->data['foo'] = 'bar';
    }

    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __isset($name): bool
    {
        return isset($this->data[$name]);
    }

    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    public function __clone()
    {
        ++$this->cloneCounter;
    }

    public function __sleep(): array
    {
        return ['data'];
    }

    public function __destruct()
    {
        ++self::$destructCounter;
    }
}
