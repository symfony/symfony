<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\AbstractExtension;
use Twig\Extension\AttributeExtension;

class AttributeExtensionTest extends TestCase
{
    public function testExtensionWithAttributes()
    {
        $kernel = new class extends AttributeExtensionKernel {
            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $loader->load(static function (ContainerBuilder $container) {
                    $container->setParameter('kernel.secret', 'secret');
                    $container->register(StaticExtensionWithAttributes::class, StaticExtensionWithAttributes::class)
                        ->setAutoconfigured(true);
                    $container->register(RuntimeExtensionWithAttributes::class, RuntimeExtensionWithAttributes::class)
                        ->setArguments(['prefix_'])
                        ->setAutoconfigured(true);

                    $container->setAlias('twig_test', 'twig')->setPublic(true);
                });
            }
        };

        $kernel->boot();

        /** @var Environment $twig */
        $twig = $kernel->getContainer()->get('twig_test');

        self::assertInstanceOf(AttributeExtension::class, $twig->getExtension(StaticExtensionWithAttributes::class));
        self::assertInstanceOf(AttributeExtension::class, $twig->getExtension(RuntimeExtensionWithAttributes::class));
        self::assertInstanceOf(RuntimeExtensionWithAttributes::class, $twig->getRuntime(RuntimeExtensionWithAttributes::class));

        self::expectException(RuntimeError::class);
        $twig->getRuntime(StaticExtensionWithAttributes::class);
    }

    public function testExtensionClassWithAttributes()
    {
        $kernel = new class extends AttributeExtensionKernel {
            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $loader->load(static function (ContainerBuilder $container) {
                    $container->setParameter('kernel.secret', 'secret');
                    $container->register(ExtensionClassWithAttributes::class, ExtensionClassWithAttributes::class)
                        ->setAutoconfigured(true);

                    $container->setAlias('twig_test', 'twig')->setPublic(true);
                });
            }
        };

        $kernel->boot();

        /** @var Environment $twig */
        $twig = $kernel->getContainer()->get('twig_test');

        $this->assertSame('foo is fun', $twig->createTemplate('{{ "foo"|fun }}')->render());
    }

    #[Before, After]
    protected function deleteTempDir()
    {
        if (file_exists($dir = sys_get_temp_dir().'/'.Kernel::VERSION.'/AttributeExtension')) {
            (new Filesystem())->remove($dir);
        }
    }
}

abstract class AttributeExtensionKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle()];
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/AttributeExtension';
    }
}

class StaticExtensionWithAttributes
{
    #[AsTwigFilter('foo')]
    public static function fooFilter(string $value): string
    {
        return $value;
    }

    #[AsTwigFunction('foo')]
    public static function fooFunction(string $value): string
    {
        return $value;
    }

    #[AsTwigTest('foo')]
    public static function fooTest(bool $value): bool
    {
        return $value;
    }
}

class RuntimeExtensionWithAttributes
{
    public function __construct(
        private string $prefix,
    ) {
    }

    #[AsTwigFilter('prefix_foo')]
    #[AsTwigFunction('prefix_foo')]
    public function prefix(string $value): string
    {
        return $this->prefix.$value;
    }
}

class ExtensionClassWithAttributes extends AbstractExtension
{
    #[AsTwigFilter('fun')]
    public function funFilter(string $value): string
    {
        return $value.' is fun';
    }
}
