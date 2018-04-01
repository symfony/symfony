<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\Bundle;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\Bundle\Bundle;
use Symphony\Component\HttpKernel\Tests\Fixtures\ExtensionNotValidBundle\ExtensionNotValidBundle;
use Symphony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\ExtensionPresentBundle;

class BundleTest extends TestCase
{
    public function testGetContainerExtension()
    {
        $bundle = new ExtensionPresentBundle();

        $this->assertInstanceOf(
            'Symphony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\DependencyInjection\ExtensionPresentExtension',
            $bundle->getContainerExtension()
        );
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage must implement Symphony\Component\DependencyInjection\Extension\ExtensionInterface
     */
    public function testGetContainerExtensionWithInvalidClass()
    {
        $bundle = new ExtensionNotValidBundle();
        $bundle->getContainerExtension();
    }

    public function testBundleNameIsGuessedFromClass()
    {
        $bundle = new GuessedNameBundle();

        $this->assertSame('Symphony\Component\HttpKernel\Tests\Bundle', $bundle->getNamespace());
        $this->assertSame('GuessedNameBundle', $bundle->getName());
    }

    public function testBundleNameCanBeExplicitlyProvided()
    {
        $bundle = new NamedBundle();

        $this->assertSame('ExplicitlyNamedBundle', $bundle->getName());
        $this->assertSame('Symphony\Component\HttpKernel\Tests\Bundle', $bundle->getNamespace());
        $this->assertSame('ExplicitlyNamedBundle', $bundle->getName());
    }
}

class NamedBundle extends Bundle
{
    public function __construct()
    {
        $this->name = 'ExplicitlyNamedBundle';
    }
}

class GuessedNameBundle extends Bundle
{
}
