<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\ParameterBag;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

class FrozenParameterBagTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testConstructor()
    {
        $parameters = [
            'foo' => 'foo',
            'bar' => 'bar',
        ];
        $bag = new FrozenParameterBag($parameters);
        $this->assertEquals($parameters, $bag->all(), '__construct() takes an array of parameters as its first argument');
    }

    public function testClear()
    {
        $this->expectException(\LogicException::class);
        $bag = new FrozenParameterBag([]);
        $bag->clear();
    }

    public function testSet()
    {
        $this->expectException(\LogicException::class);
        $bag = new FrozenParameterBag([]);
        $bag->set('foo', 'bar');
    }

    public function testAdd()
    {
        $this->expectException(\LogicException::class);
        $bag = new FrozenParameterBag([]);
        $bag->add([]);
    }

    public function testRemove()
    {
        $this->expectException(\LogicException::class);
        $bag = new FrozenParameterBag(['foo' => 'bar']);
        $bag->remove('foo');
    }

    public function testDeprecate()
    {
        $this->expectException(\LogicException::class);
        $bag = new FrozenParameterBag(['foo' => 'bar']);
        $bag->deprecate('foo', 'symfony/test', '6.3');
    }

    /**
     * The test should be kept in the group as it always expects a deprecation.
     *
     * @group legacy
     */
    public function testGetDeprecated()
    {
        $bag = new FrozenParameterBag(
            ['foo' => 'bar'],
            ['foo' => ['symfony/test', '6.3', 'The parameter "%s" is deprecated.', 'foo']]
        );

        $this->expectDeprecation('Since symfony/test 6.3: The parameter "foo" is deprecated.');

        $bag->get('foo');
    }
}
