<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Loader;

use Symfony\Bundle\TwigBundle\Loader\NativeFilesystemLoader;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Twig\Error\LoaderError;

class NativeFilesystemLoaderTest extends TestCase
{
    public function testWithNativeNamespace()
    {
        $loader = new NativeFilesystemLoader(null, __DIR__.'/../');
        $loader->addPath('Fixtures/templates', 'Test');

        $this->assertSame('Fixtures'.\DIRECTORY_SEPARATOR.'templates'.\DIRECTORY_SEPARATOR.'Foo'.\DIRECTORY_SEPARATOR.'index.html.twig', $loader->getCacheKey('@Test/Foo/index.html.twig'));
    }

    public function testWithLegacyStyle1()
    {
        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage('Template reference "TestBundle::Foo/index.html.twig" not found, did you mean "@Test/Foo/index.html.twig"?');
        $loader = new NativeFilesystemLoader(null, __DIR__.'/../');
        $loader->addPath('Fixtures/templates', 'Test');

        $loader->getCacheKey('TestBundle::Foo/index.html.twig');
    }

    public function testWithLegacyStyle2()
    {
        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage('Template reference "TestBundle:Foo:index.html.twig" not found, did you mean "@Test/Foo/index.html.twig"?');
        $loader = new NativeFilesystemLoader(null, __DIR__.'/../');
        $loader->addPath('Fixtures/templates', 'Test');

        $loader->getCacheKey('TestBundle:Foo:index.html.twig');
    }
}
