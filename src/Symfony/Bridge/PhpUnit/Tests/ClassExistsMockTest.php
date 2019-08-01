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
        $this->assertFalse(class_exists(ExistingClass::class));
        $this->assertFalse(class_exists(ExistingClass::class, false));
        $this->assertFalse(class_exists('\\'.ExistingClass::class));
        $this->assertFalse(class_exists('\\'.ExistingClass::class, false));
        $this->assertTrue(class_exists('NonExistingClass'));
        $this->assertTrue(class_exists('NonExistingClass', false));
        $this->assertTrue(class_exists('\\NonExistingClass'));
        $this->assertTrue(class_exists('\\NonExistingClass', false));
        $this->assertTrue(class_exists(ExistingClassReal::class));
        $this->assertTrue(class_exists(ExistingClassReal::class, false));
        $this->assertTrue(class_exists('\\'.ExistingClassReal::class));
        $this->assertTrue(class_exists('\\'.ExistingClassReal::class, false));
        $this->assertFalse(class_exists('NonExistingClassReal'));
        $this->assertFalse(class_exists('NonExistingClassReal', false));
        $this->assertFalse(class_exists('\\NonExistingClassReal'));
        $this->assertFalse(class_exists('\\NonExistingClassReal', false));
    }

    public function testInterfaceExists()
    {
        $this->assertFalse(interface_exists(ExistingInterface::class));
        $this->assertFalse(interface_exists(ExistingInterface::class, false));
        $this->assertFalse(interface_exists('\\'.ExistingInterface::class));
        $this->assertFalse(interface_exists('\\'.ExistingInterface::class, false));
        $this->assertTrue(interface_exists('NonExistingInterface'));
        $this->assertTrue(interface_exists('NonExistingInterface', false));
        $this->assertTrue(interface_exists('\\NonExistingInterface'));
        $this->assertTrue(interface_exists('\\NonExistingInterface', false));
        $this->assertTrue(interface_exists(ExistingInterfaceReal::class));
        $this->assertTrue(interface_exists(ExistingInterfaceReal::class, false));
        $this->assertTrue(interface_exists('\\'.ExistingInterfaceReal::class));
        $this->assertTrue(interface_exists('\\'.ExistingInterfaceReal::class, false));
        $this->assertFalse(interface_exists('NonExistingClassReal'));
        $this->assertFalse(interface_exists('NonExistingClassReal', false));
        $this->assertFalse(interface_exists('\\NonExistingInterfaceReal'));
        $this->assertFalse(interface_exists('\\NonExistingInterfaceReal', false));
    }

    public function testTraitExists()
    {
        $this->assertFalse(trait_exists(ExistingTrait::class));
        $this->assertFalse(trait_exists(ExistingTrait::class, false));
        $this->assertFalse(trait_exists('\\'.ExistingTrait::class));
        $this->assertFalse(trait_exists('\\'.ExistingTrait::class, false));
        $this->assertTrue(trait_exists('NonExistingTrait'));
        $this->assertTrue(trait_exists('NonExistingTrait', false));
        $this->assertTrue(trait_exists('\\NonExistingTrait'));
        $this->assertTrue(trait_exists('\\NonExistingTrait', false));
        $this->assertTrue(trait_exists(ExistingTraitReal::class));
        $this->assertTrue(trait_exists(ExistingTraitReal::class, false));
        $this->assertTrue(trait_exists('\\'.ExistingTraitReal::class));
        $this->assertTrue(trait_exists('\\'.ExistingTraitReal::class, false));
        $this->assertFalse(trait_exists('NonExistingClassReal'));
        $this->assertFalse(trait_exists('NonExistingClassReal', false));
        $this->assertFalse(trait_exists('\\NonExistingTraitReal'));
        $this->assertFalse(trait_exists('\\NonExistingTraitReal', false));
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
