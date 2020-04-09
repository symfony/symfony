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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * This trait is @internal.
 */
trait PolyfillTestCaseTrait
{
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
     * @param string $exception
     *
     * @return void
     */
    public function expectException($exception)
    {
        $property = new \ReflectionProperty(TestCase::class, 'expectedException');
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
        $property = new \ReflectionProperty(TestCase::class, 'expectedExceptionCode');
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
        $property = new \ReflectionProperty(TestCase::class, 'expectedExceptionMessage');
        $property->setAccessible(true);
        $property->setValue($this, $message);
    }

    /**
     * @param string $messageRegExp
     *
     * @return void
     */
    public function expectExceptionMessageMatches($messageRegExp)
    {
        $this->expectExceptionMessageRegExp($messageRegExp);
    }

    /**
     * @param string $messageRegExp
     *
     * @return void
     */
    public function expectExceptionMessageRegExp($messageRegExp)
    {
        $property = new \ReflectionProperty(TestCase::class, 'expectedExceptionMessageRegExp');
        $property->setAccessible(true);
        $property->setValue($this, $messageRegExp);
    }
}
