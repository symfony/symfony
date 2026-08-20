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
use Symfony\Component\DependencyInjection\Argument\TaggedClassMapArgument;
use Symfony\Component\DependencyInjection\Attribute\AutowireClassMap;

class AutowireClassMapTest extends TestCase
{
    public function testTagOnly()
    {
        $attribute = new AutowireClassMap('my_tag');

        $this->assertEquals(new TaggedClassMapArgument('my_tag'), $attribute->value);
    }

    public function testStringExclude()
    {
        $attribute = new AutowireClassMap('my_tag', 'key', 'Foo');

        $this->assertEquals(new TaggedClassMapArgument('my_tag', 'key', ['Foo']), $attribute->value);
    }

    public function testArrayExclude()
    {
        $attribute = new AutowireClassMap('my_tag', exclude: ['Foo', 'Bar']);

        $this->assertEquals(new TaggedClassMapArgument('my_tag', null, ['Foo', 'Bar']), $attribute->value);
    }
}
