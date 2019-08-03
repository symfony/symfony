<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

use PHPUnit\Framework\Constraint\StringContains;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
trait ForwardCompatTestTraitForV5
{
    /**
     * @return void
     */
    public static function setUpBeforeClass()
    {
        self::doSetUpBeforeClass();
    }

    /**
     * @return void
     */
    public static function tearDownAfterClass()
    {
        self::doTearDownAfterClass();
    }

    /**
     * @return void
     */
    protected function setUp()
    {
        self::doSetUp();
    }

    /**
     * @return void
     */
    protected function tearDown()
    {
        self::doTearDown();
    }

    private static function doSetUpBeforeClass()
    {
        parent::setUpBeforeClass();
    }

    private static function doTearDownAfterClass()
    {
        parent::tearDownAfterClass();
    }

    private function doSetUp()
    {
        parent::setUp();
    }

    private function doTearDown()
    {
        parent::tearDown();
    }

    /**
     * @param string|string[] $originalClassName
     *
     * @return MockObject
     */
    protected function createMock($originalClassName)
    {
        $mock = $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning();

        if (method_exists($mock, 'disallowMockingUnknownTypes')) {
            $mock = $mock->disallowMockingUnknownTypes();
        }

        return $mock->getMock();
    }

    /**
     * @param string|string[] $originalClassName
     * @param string[]        $methods
     *
     * @return MockObject
     */
    protected function createPartialMock($originalClassName, array $methods)
    {
        $mock = $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->setMethods(empty($methods) ? null : $methods);

        if (method_exists($mock, 'disallowMockingUnknownTypes')) {
            $mock = $mock->disallowMockingUnknownTypes();
        }

        return $mock->getMock();
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsArray($actual, $message = '')
    {
        static::assertInternalType('array', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsBool($actual, $message = '')
    {
        static::assertInternalType('bool', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsFloat($actual, $message = '')
    {
        static::assertInternalType('float', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsInt($actual, $message = '')
    {
        static::assertInternalType('int', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsNumeric($actual, $message = '')
    {
        static::assertInternalType('numeric', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsObject($actual, $message = '')
    {
        static::assertInternalType('object', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsResource($actual, $message = '')
    {
        static::assertInternalType('resource', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsString($actual, $message = '')
    {
        static::assertInternalType('string', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsScalar($actual, $message = '')
    {
        static::assertInternalType('scalar', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsCallable($actual, $message = '')
    {
        static::assertInternalType('callable', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsIterable($actual, $message = '')
    {
        static::assertInternalType('iterable', $actual, $message);
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param string $message
     *
     * @return void
     */
    public static function assertStringContainsString($needle, $haystack, $message = '')
    {
        $constraint = new StringContains($needle, false);
        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param string $message
     *
     * @return void
     */
    public static function assertStringContainsStringIgnoringCase($needle, $haystack, $message = '')
    {
        $constraint = new StringContains($needle, true);
        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertFinite($actual, $message = '')
    {
        if (method_exists(TestCase::class, 'assertFinite')) {
            parent::assertFinite($actual, $message);

            return;
        }

        static::assertInternalType('float', $actual, $message);
        static::assertTrue(is_finite($actual), $message ? $message : "Failed asserting that $actual is finite.");
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertInfinite($actual, $message = '')
    {
        if (method_exists(TestCase::class, 'assertInfinite')) {
            parent::assertInfinite($actual, $message);

            return;
        }

        static::assertInternalType('float', $actual, $message);
        static::assertTrue(is_infinite($actual), $message ? $message : "Failed asserting that $actual is infinite.");
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertNan($actual, $message = '')
    {
        if (method_exists(TestCase::class, 'assertNan')) {
            parent::assertNan($actual, $message);

            return;
        }

        static::assertInternalType('float', $actual, $message);
        static::assertTrue(is_nan($actual), $message ? $message : "Failed asserting that $actual is nan.");
    }

    /**
     * @param string $exception
     *
     * @return void
     */
    public function expectException($exception)
    {
        if (method_exists(TestCase::class, 'expectException')) {
            parent::expectException($exception);

            return;
        }

        $property = new \ReflectionProperty(class_exists('PHPUnit_Framework_TestCase') ? 'PHPUnit_Framework_TestCase' : TestCase::class, 'expectedException');
        $property->setAccessible(true);
        $property->setValue($this, $exception);
    }

    /**
     * @param int|string $code
     *
     * @return void
     */
    public function expectExceptionCode($code)
    {
        if (method_exists(TestCase::class, 'expectExceptionCode')) {
            parent::expectExceptionCode($code);

            return;
        }

        $property = new \ReflectionProperty(class_exists('PHPUnit_Framework_TestCase') ? 'PHPUnit_Framework_TestCase' : TestCase::class, 'expectedExceptionCode');
        $property->setAccessible(true);
        $property->setValue($this, $code);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function expectExceptionMessage($message)
    {
        if (method_exists(TestCase::class, 'expectExceptionMessage')) {
            parent::expectExceptionMessage($message);

            return;
        }

        $property = new \ReflectionProperty(class_exists('PHPUnit_Framework_TestCase') ? 'PHPUnit_Framework_TestCase' : TestCase::class, 'expectedExceptionMessage');
        $property->setAccessible(true);
        $property->setValue($this, $message);
    }

    /**
     * @param string $messageRegExp
     *
     * @return void
     */
    public function expectExceptionMessageRegExp($messageRegExp)
    {
        if (method_exists(TestCase::class, 'expectExceptionMessageRegExp')) {
            parent::expectExceptionMessageRegExp($messageRegExp);

            return;
        }

        $property = new \ReflectionProperty(class_exists('PHPUnit_Framework_TestCase') ? 'PHPUnit_Framework_TestCase' : TestCase::class, 'expectedExceptionMessageRegExp');
        $property->setAccessible(true);
        $property->setValue($this, $messageRegExp);
    }
}
