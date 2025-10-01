<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\DependencyInjection\Reference;

class AutowireMethodOfTest extends TestCase
{
    public function testConstructor()
    {
        $a = new AutowireMethodOf('foo');

        $this->assertEquals([new Reference('foo')], $a->value);
    }

    public function testBuildDefinition(?\Closure $dummy = null)
    {
        $a = new AutowireMethodOf('foo');
        $r = new \ReflectionParameter([__CLASS__, __FUNCTION__], 0);

        $this->assertEquals([[new Reference('foo'), 'dummy']], $a->buildDefinition($a->value, 'Closure', $r)->getArguments());
    }
}
