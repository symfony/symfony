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

    /**
     * @return void
     */
    private static function doSetUpBeforeClass()
    {
        parent::setUpBeforeClass();
    }

    /**
     * @return void
     */
    private static function doTearDownAfterClass()
    {
        parent::tearDownAfterClass();
    }

    /**
     * @return void
     */
    private function doSetUp()
    {
        parent::setUp();
    }

    /**
     * @return void
     */
    private function doTearDown()
    {
        parent::tearDown();
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
}
