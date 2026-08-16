<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Argument;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedClassMapArgument;

class TaggedClassMapArgumentTest extends TestCase
{
    public function testWithIndexAttribute()
    {
        $argument = new TaggedClassMapArgument('app.my_tag', 'key', ['Foo', 'Bar']);

        $this->assertSame('app.my_tag', $argument->getTag());
        $this->assertSame('key', $argument->getIndexAttribute());
        $this->assertSame(['Foo', 'Bar'], $argument->getExclude());
    }

    public function testIndexAttributeDefaultsToTagLastDotSegment()
    {
        $argument = new TaggedClassMapArgument('app.my_tag');

        $this->assertSame('my_tag', $argument->getIndexAttribute());
        $this->assertSame([], $argument->getExclude());
    }

    public function testIndexAttributeDefaultsToTagWithoutDots()
    {
        $argument = new TaggedClassMapArgument('my_tag');

        $this->assertSame('my_tag', $argument->getIndexAttribute());
    }

    public function testSetValues()
    {
        $argument = new TaggedClassMapArgument('my_tag');

        $this->assertSame([], $argument->getValues());

        $argument->setValues(['key' => 'Foo']);

        $this->assertSame(['key' => 'Foo'], $argument->getValues());
    }
}
