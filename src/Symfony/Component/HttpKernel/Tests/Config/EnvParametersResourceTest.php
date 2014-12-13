<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Config;

use Symfony\Component\HttpKernel\Config\EnvParametersResource;

class EnvParametersResourceTest extends \PHPUnit_Framework_TestCase
{
    protected $prefix = '__DUMMY_';
    protected $initialEnv;
    protected $resource;

    protected function setUp()
    {
        $this->initialEnv = array(
            $this->prefix.'1' => 'foo',
            $this->prefix.'2' => 'bar',
        );

        foreach ($this->initialEnv as $key => $value) {
            $_SERVER[$key] = $value;
        }

        $this->resource = new EnvParametersResource($this->prefix);
    }

    protected function tearDown()
    {
        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, $this->prefix)) {
                unset($_SERVER[$key]);
            }
        }
    }

    public function testGetResource()
    {
        $this->assertSame(
            array('prefix' => $this->prefix, 'variables' => $this->initialEnv),
            $this->resource->getResource(),
            '->getResource() returns the resource'
        );
    }

    public function testToString()
    {
        $this->assertSame(
            serialize(array('prefix' => $this->prefix, 'variables' => $this->initialEnv)),
            (string) $this->resource
        );
    }

    public function testIsFreshNotChanged()
    {
        $this->assertTrue(
            $this->resource->isFresh(time()),
            '->isFresh() returns true if the variables have not changed'
        );
    }

    public function testIsFreshValueChanged()
    {
        reset($this->initialEnv);
        $_SERVER[key($this->initialEnv)] = 'baz';

        $this->assertFalse(
            $this->resource->isFresh(time()),
            '->isFresh() returns false if a variable has been changed'
        );
    }

    public function testIsFreshValueRemoved()
    {
        reset($this->initialEnv);
        unset($_SERVER[key($this->initialEnv)]);

        $this->assertFalse(
            $this->resource->isFresh(time()),
            '->isFresh() returns false if a variable has been removed'
        );
    }

    public function testIsFreshValueAdded()
    {
        $_SERVER[$this->prefix.'3'] = 'foo';

        $this->assertFalse(
            $this->resource->isFresh(time()),
            '->isFresh() returns false if a variable has been added'
        );
    }

    public function testSerializeUnserialize()
    {
        $this->assertEquals($this->resource, unserialize(serialize($this->resource)));
    }
}
