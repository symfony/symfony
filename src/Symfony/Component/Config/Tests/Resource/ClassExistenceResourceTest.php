<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Resource;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\Config\Tests\Fixtures\BadFileName;
use Symfony\Component\Config\Tests\Fixtures\BadParent;
use Symfony\Component\Config\Tests\Fixtures\ParseError;
use Symfony\Component\Config\Tests\Fixtures\Resource\ConditionalClass;

class ClassExistenceResourceTest extends TestCase
{
    public function testToString()
    {
        $res = new ClassExistenceResource('BarClass');
        self::assertSame('BarClass', (string) $res);
    }

    public function testGetResource()
    {
        $res = new ClassExistenceResource('BarClass');
        self::assertSame('BarClass', $res->getResource());
    }

    public function testIsFreshWhenClassDoesNotExist()
    {
        $res = new ClassExistenceResource('Symfony\Component\Config\Tests\Fixtures\BarClass');

        self::assertTrue($res->isFresh(time()));

        eval(<<<EOF
namespace Symfony\Component\Config\Tests\Fixtures;

class BarClass
{
}
EOF
        );

        self::assertFalse($res->isFresh(time()));
    }

    public function testIsFreshWhenClassExists()
    {
        $res = new ClassExistenceResource('Symfony\Component\Config\Tests\Resource\ClassExistenceResourceTest');

        self::assertTrue($res->isFresh(time()));
    }

    public function testExistsKo()
    {
        spl_autoload_register($autoloader = function ($class) use (&$loadedClass) { $loadedClass = $class; });

        try {
            $res = new ClassExistenceResource('MissingFooClass');
            self::assertTrue($res->isFresh(0));

            self::assertSame('MissingFooClass', $loadedClass);

            $loadedClass = 123;

            new ClassExistenceResource('MissingFooClass', false);

            self::assertSame(123, $loadedClass);
        } finally {
            spl_autoload_unregister($autoloader);
        }
    }

    public function testBadParentWithTimestamp()
    {
        $res = new ClassExistenceResource(BadParent::class, false);
        self::assertTrue($res->isFresh(time()));
    }

    public function testBadParentWithNoTimestamp()
    {
        self::expectException(\ReflectionException::class);
        self::expectExceptionMessage('Class "Symfony\Component\Config\Tests\Fixtures\MissingParent" not found while loading "Symfony\Component\Config\Tests\Fixtures\BadParent".');

        $res = new ClassExistenceResource(BadParent::class, false);
        $res->isFresh(0);
    }

    public function testBadFileName()
    {
        self::expectException(\ReflectionException::class);
        self::expectExceptionMessage('Mismatch between file name and class name.');

        $res = new ClassExistenceResource(BadFileName::class, false);
        $res->isFresh(0);
    }

    public function testBadFileNameBis()
    {
        self::expectException(\ReflectionException::class);
        self::expectExceptionMessage('Mismatch between file name and class name.');

        $res = new ClassExistenceResource(BadFileName::class, false);
        $res->isFresh(0);
    }

    public function testConditionalClass()
    {
        $res = new ClassExistenceResource(ConditionalClass::class, false);

        self::assertFalse($res->isFresh(0));
    }

    public function testParseError()
    {
        self::expectException(\ParseError::class);

        $res = new ClassExistenceResource(ParseError::class, false);
        $res->isFresh(0);
    }
}
