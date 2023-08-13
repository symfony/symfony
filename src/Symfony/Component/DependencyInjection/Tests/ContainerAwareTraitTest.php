<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ContainerAwareDummy;

/**
 * @group legacy
 */
class ContainerAwareTraitTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testSetContainerLegacy()
    {
        $container = $this->createMock(ContainerInterface::class);

        $dummy = new ContainerAwareDummy();
        $dummy->setContainer($container);

        self::assertSame($container, $dummy->getContainer());

        $this->expectDeprecation('Since symfony/dependency-injection 6.2: Calling "Symfony\Component\DependencyInjection\Tests\Fixtures\ContainerAwareDummy::setContainer()" without any arguments is deprecated, pass null explicitly instead.');

        $dummy->setContainer();
        self::assertNull($dummy->getContainer());
    }

    public function testSetContainer()
    {
        $container = $this->createMock(ContainerInterface::class);

        $dummy = new ContainerAwareDummy();
        $dummy->setContainer($container);

        self::assertSame($container, $dummy->getContainer());

        $dummy->setContainer(null);
        self::assertNull($dummy->getContainer());
    }
}
