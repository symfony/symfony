<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class AbstractTypeExtensionTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testImplementingNeitherGetExtendedTypeNorExtendsTypeThrowsException()
    {
        $this->expectException('Symfony\Component\Form\Exception\LogicException');
        $this->expectExceptionMessage('You need to implement the static getExtendedTypes() method when implementing the Symfony\Component\Form\FormTypeExtensionInterface in Symfony\Component\Form\Tests\TypeExtensionWithoutExtendedTypes.');
        $extension = new TypeExtensionWithoutExtendedTypes();
        $extension->getExtendedType();
    }

    /**
     * @group legacy
     * @expectedDeprecation The Symfony\Component\Form\Tests\MultipleTypesExtension::getExtendedType() method is deprecated since Symfony 4.2 and will be removed in 5.0. Use getExtendedTypes() instead.
     */
    public function testGetExtendedTypeReturnsFirstConfiguredExtension()
    {
        $extension = new MultipleTypesExtension();

        $this->assertSame(DateTimeType::class, $extension->getExtendedType());
    }
}
