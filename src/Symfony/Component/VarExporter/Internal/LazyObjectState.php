<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Internal;

/**
 * Keeps the state of lazy objects.
 *
 * As a micro-optimization, this class uses no type declarations.
 *
 * @internal
 */
class LazyObjectState
{
    public ?\Closure $initializer = null;
    public object $realInstance;
    public object $cloneInstance;

    public function __clone()
    {
        if (isset($this->cloneInstance)) {
            try {
                $this->realInstance = $this->cloneInstance;
            } finally {
                unset($this->cloneInstance);
            }
        } elseif (isset($this->realInstance)) {
            $this->realInstance = clone $this->realInstance;
        }
    }

    public function __get($name)
    {
        if ('realInstance' !== $name) {
            throw new \BadMethodCallException(\sprintf('No such property "%s::$%s"', self::class, $name));
        }

        return $this->realInstance = ($this->initializer)();
    }
}
