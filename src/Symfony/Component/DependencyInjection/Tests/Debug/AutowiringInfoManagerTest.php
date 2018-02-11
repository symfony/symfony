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
use Symfony\Component\DependencyInjection\Debug\AutowiringInfoManager;
use Symfony\Component\DependencyInjection\Debug\AutowiringInfoProviderInterface;
use Symfony\Component\DependencyInjection\Debug\AutowiringTypeInfo;

class AutowiringInfoManagerTest extends TestCase
{
    public function testGetInfo()
    {
        $infoFoo = AutowiringTypeInfo::create('FooInterface', 'Foo');
        $infoBar = AutowiringTypeInfo::create('BarInterface', 'Bar');
        $infoBaz = AutowiringTypeInfo::create('BazInterface', 'Baz');

        $provider1 = $this->createMock(AutowiringInfoProviderInterface::class);
        $provider1->expects($this->once())
            ->method('getTypeInfos')
            ->willReturn(array($infoFoo, $infoBar));

        $provider2 = $this->createMock(AutowiringInfoProviderInterface::class);
        $provider2->expects($this->once())
            ->method('getTypeInfos')
            ->willReturn(array($infoBaz));

        $manager = new AutowiringInfoManager(array($provider1, $provider2));
        $this->assertSame($infoFoo, $manager->getInfo('FooInterface'));
        $this->assertSame($infoBar, $manager->getInfo('BarInterface'));
        $this->assertSame($infoBaz, $manager->getInfo('BazInterface'));
        $this->assertNull($manager->getInfo('InventedClass'));
    }
}
