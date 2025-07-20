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

use Symfony\Bundle\FrameworkBundle\Tests\Functional\AbstractWebTestCase;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarTagClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooTagClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ServiceLocatorWithAttribute;
use Symfony\Component\HttpKernel\KernelInterface;

class AsServiceLocatorAttributeTest extends AbstractWebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['test_case' => 'AsServiceLocator'] + $options);
    }

    public function testCanOnlySetOneParameter()
    {
        $container = self::getContainer();

        $locator = $container->get(ServiceLocatorWithAttribute::class);

        self::assertSame([FooTagClass::class, BarTagClass::class], array_values($locator->getProvidedServices()));
    }
}
