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
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class AbstractTypeExtensionTest extends TestCase
{
    public function testImplementingNeitherGetExtendedTypeNorExtendsTypeThrowsException()
    {
        $this->expectException('Symfony\Component\Form\Exception\LogicException');
        $this->expectExceptionMessage('You need to implement the static getExtendedTypes() method when implementing the Symfony\Component\Form\FormTypeExtensionInterface in Symfony\Component\Form\Tests\TypeExtensionWithoutExtendedTypes.');
        $extension = new TypeExtensionWithoutExtendedTypes();
        $extension->getExtendedType();
    }

    /**
     * @group legacy
     */
    public function testGetExtendedTypeReturnsFirstConfiguredExtension()
    {
        $extension = new MultipleTypesExtension();

        $this->assertSame(DateTimeType::class, $extension->getExtendedType());
    }
}

class MultipleTypesExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        yield DateTimeType::class;
        yield DateType::class;
    }
}

class TypeExtensionWithoutExtendedTypes extends AbstractTypeExtension
{
}
