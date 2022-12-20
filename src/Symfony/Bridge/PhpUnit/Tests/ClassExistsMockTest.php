<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClassExistsMock;

class ClassExistsMockTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClassExistsMock::register(__CLASS__);
    }

    protected function setUp(): void
    {
        ClassExistsMock::withMockedClasses([
            ExistingClass::class => false,
            'NonExistingClass' => true,
            ExistingInterface::class => false,
            'NonExistingInterface' => true,
            ExistingTrait::class => false,
            'NonExistingTrait' => true,
        ]);
    }

    public function testClassExists()
    {
        self::assertFalse(class_exists(ExistingClass::class));
        self::assertFalse(class_exists(ExistingClass::class, false));
        self::assertFalse(class_exists('\\'.ExistingClass::class));
        self::assertFalse(class_exists('\\'.ExistingClass::class, false));
        self::assertTrue(class_exists('NonExistingClass'));
        self::assertTrue(class_exists('NonExistingClass', false));
        self::assertTrue(class_exists('\\NonExistingClass'));
        self::assertTrue(class_exists('\\NonExistingClass', false));
        self::assertTrue(class_exists(ExistingClassReal::class));
        self::assertTrue(class_exists(ExistingClassReal::class, false));
        self::assertTrue(class_exists('\\'.ExistingClassReal::class));
        self::assertTrue(class_exists('\\'.ExistingClassReal::class, false));
        self::assertFalse(class_exists('NonExistingClassReal'));
        self::assertFalse(class_exists('NonExistingClassReal', false));
        self::assertFalse(class_exists('\\NonExistingClassReal'));
        self::assertFalse(class_exists('\\NonExistingClassReal', false));
    }

    public function testInterfaceExists()
    {
        self::assertFalse(interface_exists(ExistingInterface::class));
        self::assertFalse(interface_exists(ExistingInterface::class, false));
        self::assertFalse(interface_exists('\\'.ExistingInterface::class));
        self::assertFalse(interface_exists('\\'.ExistingInterface::class, false));
        self::assertTrue(interface_exists('NonExistingInterface'));
        self::assertTrue(interface_exists('NonExistingInterface', false));
        self::assertTrue(interface_exists('\\NonExistingInterface'));
        self::assertTrue(interface_exists('\\NonExistingInterface', false));
        self::assertTrue(interface_exists(ExistingInterfaceReal::class));
        self::assertTrue(interface_exists(ExistingInterfaceReal::class, false));
        self::assertTrue(interface_exists('\\'.ExistingInterfaceReal::class));
        self::assertTrue(interface_exists('\\'.ExistingInterfaceReal::class, false));
        self::assertFalse(interface_exists('NonExistingClassReal'));
        self::assertFalse(interface_exists('NonExistingClassReal', false));
        self::assertFalse(interface_exists('\\NonExistingInterfaceReal'));
        self::assertFalse(interface_exists('\\NonExistingInterfaceReal', false));
    }

    public function testTraitExists()
    {
        self::assertFalse(trait_exists(ExistingTrait::class));
        self::assertFalse(trait_exists(ExistingTrait::class, false));
        self::assertFalse(trait_exists('\\'.ExistingTrait::class));
        self::assertFalse(trait_exists('\\'.ExistingTrait::class, false));
        self::assertTrue(trait_exists('NonExistingTrait'));
        self::assertTrue(trait_exists('NonExistingTrait', false));
        self::assertTrue(trait_exists('\\NonExistingTrait'));
        self::assertTrue(trait_exists('\\NonExistingTrait', false));
        self::assertTrue(trait_exists(ExistingTraitReal::class));
        self::assertTrue(trait_exists(ExistingTraitReal::class, false));
        self::assertTrue(trait_exists('\\'.ExistingTraitReal::class));
        self::assertTrue(trait_exists('\\'.ExistingTraitReal::class, false));
        self::assertFalse(trait_exists('NonExistingClassReal'));
        self::assertFalse(trait_exists('NonExistingClassReal', false));
        self::assertFalse(trait_exists('\\NonExistingTraitReal'));
        self::assertFalse(trait_exists('\\NonExistingTraitReal', false));
    }
}

class ExistingClass
{
}

class ExistingClassReal
{
}

interface ExistingInterface
{
}

interface ExistingInterfaceReal
{
}

trait ExistingTrait
{
}

trait ExistingTraitReal
{
}
