<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Debug\AutowiringTypeInfo;

class AutowiringTypeInfoTest extends TestCase
{
    public function testBasicFunctionality()
    {
        $info = AutowiringTypeInfo::create('Foo\\CoolStuffType', 'Cool Stuff');
        $this->assertSame($info->getType(), 'Foo\\CoolStuffType');
        $this->assertSame($info->getName(), 'Cool Stuff');
        $this->assertSame(0, $info->getPriority());
        $this->assertSame('', $info->getDescription());

        $info = AutowiringTypeInfo::create('Foo\\CoolStuffType', 'Cool Stuff', 10)
            ->setDescription('Some really cool stuff');

        $this->assertSame(10, $info->getPriority());
        $this->assertSame('Some really cool stuff', $info->getDescription());
    }
}
