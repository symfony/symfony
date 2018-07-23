<?php

namespace Symfony\Bundle\TwigBundle\Tests\Loader;

use Symfony\Bundle\TwigBundle\Loader\NativeFilesystemLoader;
use Symfony\Bundle\TwigBundle\Tests\TestCase;

class NativeFilesystemLoaderTest extends TestCase
{
    public function testWithNativeNamespace()
    {
        $loader = new NativeFilesystemLoader(null, __DIR__.'/../');
        $loader->addPath('Fixtures/templates', 'Test');

        $this->assertSame('Fixtures'.\DIRECTORY_SEPARATOR.'templates'.\DIRECTORY_SEPARATOR.'Foo'.\DIRECTORY_SEPARATOR.'index.html.twig', $loader->getCacheKey('@Test/Foo/index.html.twig'));
    }

    /**
     * @expectedException \Twig\Error\LoaderError
     * @expectedExceptionMessage Template reference "TestBundle::Foo/index.html.twig" not found, did you mean "@Test/Foo/index.html.twig"?
     */
    public function testWithLegacyStyle1()
    {
        $loader = new NativeFilesystemLoader(null, __DIR__.'/../');
        $loader->addPath('Fixtures/templates', 'Test');

        $loader->getCacheKey('TestBundle::Foo/index.html.twig');
    }

    /**
     * @expectedException \Twig\Error\LoaderError
     * @expectedExceptionMessage Template reference "TestBundle:Foo:index.html.twig" not found, did you mean "@Test/Foo/index.html.twig"?
     */
    public function testWithLegacyStyle2()
    {
        $loader = new NativeFilesystemLoader(null, __DIR__.'/../');
        $loader->addPath('Fixtures/templates', 'Test');

        $loader->getCacheKey('TestBundle:Foo:index.html.twig');
    }
}
