<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Factory;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;

class UuidFactory
{
    private $defaultClass;
    private $timeBasedClass;
    private $nameBasedClass;
    private $randomBasedClass;
    private $timeBasedNode;
    private $nameBasedNamespace;

    /**
     * @param string|int       $defaultClass
     * @param string|int       $timeBasedClass
     * @param string|int       $nameBasedClass
     * @param string|int       $randomBasedClass
     * @param Uuid|string|null $timeBasedNode
     * @param Uuid|string|null $nameBasedNamespace
     */
    public function __construct($defaultClass = UuidV6::class, $timeBasedClass = UuidV6::class, $nameBasedClass = UuidV5::class, $randomBasedClass = UuidV4::class, $timeBasedNode = null, $nameBasedNamespace = null)
    {
        if (null !== $timeBasedNode && !$timeBasedNode instanceof Uuid) {
            $timeBasedNode = Uuid::fromString($timeBasedNode);
        }

        if (null !== $nameBasedNamespace && !$nameBasedNamespace instanceof Uuid) {
            $nameBasedNamespace = Uuid::fromString($nameBasedNamespace);
        }

        $this->defaultClass = is_numeric($defaultClass) ? Uuid::class.'V'.$defaultClass : $defaultClass;
        $this->timeBasedClass = is_numeric($timeBasedClass) ? Uuid::class.'V'.$timeBasedClass : $timeBasedClass;
        $this->nameBasedClass = is_numeric($nameBasedClass) ? Uuid::class.'V'.$nameBasedClass : $nameBasedClass;
        $this->randomBasedClass = is_numeric($randomBasedClass) ? Uuid::class.'V'.$randomBasedClass : $randomBasedClass;
        $this->timeBasedNode = $timeBasedNode;
        $this->nameBasedNamespace = $nameBasedNamespace;
    }

    /**
     * @return UuidV6|UuidV4|UuidV1
     */
    public function create(): Uuid
    {
        $class = $this->defaultClass;

        return new $class();
    }

    public function randomBased(): RandomBasedUuidFactory
    {
        return new RandomBasedUuidFactory($this->randomBasedClass);
    }

    /**
     * @param Uuid|string|null $node
     */
    public function timeBased($node = null): TimeBasedUuidFactory
    {
        $node ?? $node = $this->timeBasedNode;

        if (null === $node) {
            $class = $this->timeBasedClass;
            $node = $this->timeBasedNode = new $class();
        } elseif (!$node instanceof Uuid) {
            $node = Uuid::fromString($node);
        }

        return new TimeBasedUuidFactory($this->timeBasedClass, $node);
    }

    /**
     * @param Uuid|string|null $namespace
     */
    public function nameBased($namespace = null): NameBasedUuidFactory
    {
        $namespace ?? $namespace = $this->nameBasedNamespace;

        if (null === $namespace) {
            throw new \LogicException(sprintf('A namespace should be defined when using "%s()".', __METHOD__));
        }

        if (!$namespace instanceof Uuid) {
            $namespace = Uuid::fromString($namespace);
        }

        return new NameBasedUuidFactory($this->nameBasedClass, $namespace);
    }
}
