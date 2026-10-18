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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\AttributeExtension;
use Twig\TwigFunction;

class LazyExtensionTest extends TestCase
{
    #[DataProvider('provideExtensions')]
    public function testExtensionDependenciesAreCreatedOnFirstUse(string $class, string $function)
    {
        $kernel = new LazyExtensionKernel();
        $kernel->boot();

        /** @var Environment $twig */
        $twig = $kernel->getContainer()->get('twig_test');

        $this->assertSame('foo', $twig->createTemplate('{{ "foo" }}')->render());
        $this->assertSame(0, ExtensionDependency::$instances);

        $this->assertSame('bar', $twig->createTemplate('{{ '.$function.'() }}')->render());
        $this->assertSame(1, ExtensionDependency::$instances);

        $extension = $twig->getExtension($class);

        $this->assertInstanceOf($class, $extension);
        $this->assertSame($extension, $twig->getExtension($class));
        $this->assertSame(1, ExtensionDependency::$instances);
    }

    public static function provideExtensions(): iterable
    {
        yield [ExtensionWithDependency::class, 'dependency_value'];
        yield [AttributeExtensionWithDependency::class, 'attribute_dependency_value'];
        yield [DelegatingAttributeExtensionWithDependency::class, 'delegating_dependency_value'];

        if (!(new \ReflectionClass(AttributeExtension::class))->isFinal()) {
            yield [AttributeExtensionSubclassWithDependency::class, 'subclass_dependency_value'];
        }
    }

    #[Before, After]
    protected function reset()
    {
        ExtensionDependency::$instances = 0;

        if (file_exists($dir = sys_get_temp_dir().'/'.Kernel::VERSION.'/LazyExtension')) {
            (new Filesystem())->remove($dir);
        }
    }
}

class LazyExtensionKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container) {
            $container->setParameter('kernel.secret', 'secret');
            $container->register(ExtensionDependency::class, ExtensionDependency::class);
            $container->register(ExtensionWithDependency::class, ExtensionWithDependency::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
            $container->register(AttributeExtensionWithDependency::class, AttributeExtensionWithDependency::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
            $container->register(DelegatingAttributeExtensionWithDependency::class, DelegatingAttributeExtensionWithDependency::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);

            if (class_exists(AttributeExtensionSubclassWithDependency::class, false)) {
                $container->register(AttributeExtensionSubclassWithDependency::class, AttributeExtensionSubclassWithDependency::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true);
            }

            $container->setAlias('twig_test', 'twig')->setPublic(true);
        });
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/LazyExtension';
    }
}

class ExtensionDependency
{
    public static int $instances = 0;

    public function __construct()
    {
        ++self::$instances;
    }

    public function getValue(): string
    {
        return 'bar';
    }
}

class ExtensionWithDependency extends AbstractExtension
{
    public function __construct(
        private ExtensionDependency $dependency,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('dependency_value', $this->getValue(...))];
    }

    public function getValue(): string
    {
        return $this->dependency->getValue();
    }
}

class AttributeExtensionWithDependency extends AbstractExtension
{
    public function __construct(
        private ExtensionDependency $dependency,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('attribute_dependency_value', $this->getValue(...))];
    }

    #[AsTwigFunction('attribute_dependency_value')]
    public function getValue(): string
    {
        return $this->dependency->getValue();
    }
}

class DelegatingAttributeExtensionWithDependency extends AbstractExtension
{
    public function __construct(
        private ExtensionDependency $dependency,
    ) {
    }

    public function getFunctions(): array
    {
        return (new AttributeExtension(self::class))->getFunctions();
    }

    #[AsTwigFunction('delegating_dependency_value')]
    public function getValue(): string
    {
        return $this->dependency->getValue();
    }
}

if (!(new \ReflectionClass(AttributeExtension::class))->isFinal()) {
    class AttributeExtensionSubclassWithDependency extends AttributeExtension
    {
        public function __construct(
            private ExtensionDependency $dependency,
        ) {
        }

        #[AsTwigFunction('subclass_dependency_value')]
        public function getValue(): string
        {
            return $this->dependency->getValue();
        }
    }
}
