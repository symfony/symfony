<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

#[\AllowDynamicProperties]
class ObjectDummy
{
    protected $foo;
    /**
     * @var array
     */
    public $bar;
    private $baz;
    protected $camelCase;
    protected $object;
    private $go;

    public function getFoo()
    {
        return $this->foo;
    }

    public function setFoo($foo): void
    {
        $this->foo = $foo;
    }

    public function isBaz()
    {
        return $this->baz;
    }

    public function setBaz($baz): void
    {
        $this->baz = $baz;
    }

    public function getFooBar()
    {
        return $this->foo.$this->bar;
    }

    public function getCamelCase()
    {
        return $this->camelCase;
    }

    public function setCamelCase($camelCase): void
    {
        $this->camelCase = $camelCase;
    }

    public function otherMethod(): void
    {
        throw new \RuntimeException('Dummy::otherMethod() should not be called');
    }

    public function setObject($object): void
    {
        $this->object = $object;
    }

    public function getObject()
    {
        return $this->object;
    }

    public function setGo($go): void
    {
        $this->go = $go;
    }

    public function canGo()
    {
        return $this->go;
    }
}
