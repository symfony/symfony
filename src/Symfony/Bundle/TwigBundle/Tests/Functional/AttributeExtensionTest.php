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
use Twig\Extension\AttributeExtension;

class AttributeExtensionTest extends TestCase
{
    public function testExtensionWithAttributes()
    {
        if (!class_exists(AttributeExtension::class)) {
            self::markTestSkipped('Twig 3.21 is required.');
        }

        $kernel = new class('test', true) extends Kernel
        {
            public function registerBundles(): iterable
            {
                return [new FrameworkBundle(), new TwigBundle()];
            }

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

            public function getProjectDir(): string
            {
                return sys_get_temp_dir().'/'.Kernel::VERSION.'/AttributeExtension';
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

    /**
     * @before
     * @after
     */
    protected function deleteTempDir()
    {
        if (file_exists($dir = sys_get_temp_dir().'/'.Kernel::VERSION.'/AttributeExtension')) {
            (new Filesystem())->remove($dir);
        }
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
    public function __construct(private bool $prefix)
    {
    }

    #[AsTwigFilter('foo')]
    #[AsTwigFunction('foo')]
    public function prefix(string $value): string
    {
        return $this->prefix.$value;
    }
}
