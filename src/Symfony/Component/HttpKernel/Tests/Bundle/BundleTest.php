<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Bundle;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\ExtensionPresentBundle;

class BundleTest extends TestCase
{
    public function testGetContainerExtension()
    {
        $bundle = new ExtensionPresentBundle();

        $this->assertInstanceOf(
            'Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\DependencyInjection\ExtensionPresentExtension',
            $bundle->getContainerExtension()
        );
    }

    public function testBundleNameIsGuessedFromClass()
    {
        $bundle = new GuessedNameBundle();

        $this->assertSame('Symfony\Component\HttpKernel\Tests\Bundle', $bundle->getNamespace());
        $this->assertSame('GuessedNameBundle', $bundle->getName());
    }

    public function testBundleNameCanBeExplicitlyProvided()
    {
        $bundle = new NamedBundle();

        $this->assertSame('ExplicitlyNamedBundle', $bundle->getName());
        $this->assertSame('Symfony\Component\HttpKernel\Tests\Bundle', $bundle->getNamespace());
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
