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
use Symfony\Bridge\PhpUnit\Tests\Fixtures\ExistingEnum;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\ExistingEnumReal;

/**
 * @requires PHP 8.1
 */
class EnumExistsMockTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Require the fixture file to allow PHP to be fully aware of the enum existence
        require __DIR__.'/Fixtures/ExistingEnumReal.php';

        ClassExistsMock::register(__CLASS__);
    }

    protected function setUp(): void
    {
        ClassExistsMock::withMockedEnums([
            ExistingEnum::class => false,
            'NonExistingEnum' => true,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        ClassExistsMock::withMockedEnums([]);
    }

    public function testClassExists()
    {
        $this->assertFalse(class_exists(ExistingEnum::class));
        $this->assertFalse(class_exists(ExistingEnum::class, false));
        $this->assertFalse(class_exists('\\'.ExistingEnum::class));
        $this->assertFalse(class_exists('\\'.ExistingEnum::class, false));
        $this->assertTrue(class_exists('NonExistingEnum'));
        $this->assertTrue(class_exists('NonExistingEnum', false));
        $this->assertTrue(class_exists('\\NonExistingEnum'));
        $this->assertTrue(class_exists('\\NonExistingEnum', false));
        $this->assertTrue(class_exists(ExistingEnumReal::class));
        $this->assertTrue(class_exists(ExistingEnumReal::class, false));
        $this->assertTrue(class_exists('\\'.ExistingEnumReal::class));
        $this->assertTrue(class_exists('\\'.ExistingEnumReal::class, false));
        $this->assertFalse(class_exists('\\NonExistingEnumReal'));
        $this->assertFalse(class_exists('\\NonExistingEnumReal', false));
    }

    public function testEnumExists()
    {
        $this->assertFalse(enum_exists(ExistingEnum::class));
        $this->assertFalse(enum_exists(ExistingEnum::class, false));
        $this->assertFalse(enum_exists('\\'.ExistingEnum::class));
        $this->assertFalse(enum_exists('\\'.ExistingEnum::class, false));
        $this->assertTrue(enum_exists('NonExistingEnum'));
        $this->assertTrue(enum_exists('NonExistingEnum', false));
        $this->assertTrue(enum_exists('\\NonExistingEnum'));
        $this->assertTrue(enum_exists('\\NonExistingEnum', false));
        $this->assertTrue(enum_exists(ExistingEnumReal::class));
        $this->assertTrue(enum_exists(ExistingEnumReal::class, false));
        $this->assertTrue(enum_exists('\\'.ExistingEnumReal::class));
        $this->assertTrue(enum_exists('\\'.ExistingEnumReal::class, false));
        $this->assertFalse(enum_exists('NonExistingClassReal'));
        $this->assertFalse(enum_exists('NonExistingClassReal', false));
        $this->assertFalse(enum_exists('\\NonExistingEnumReal'));
        $this->assertFalse(enum_exists('\\NonExistingEnumReal', false));
    }
}
