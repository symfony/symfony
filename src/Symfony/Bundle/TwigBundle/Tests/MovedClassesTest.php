<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\Controller\TemplateController;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

class MovedClassesTest extends TestCase
{
    private const LEGACY_TEMPLATE_CONTROLLER = 'Symfony\Bundle\FrameworkBundle\Controller\TemplateController';

    #[RunInSeparateProcess]
    public function testTemplateControllerSatisfiesTheLegacyType()
    {
        $this->assertFalse(class_exists(self::LEGACY_TEMPLATE_CONTROLLER, false));
        $this->assertTrue(is_a(TemplateController::class, self::LEGACY_TEMPLATE_CONTROLLER, true));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[RunInSeparateProcess]
    public function testLoadingTheLegacyNameFirstTriggersADeprecation()
    {
        $this->assertFalse(class_exists(self::LEGACY_TEMPLATE_CONTROLLER, false));
        $this->assertFalse(class_exists(TemplateController::class, false));
        $this->expectUserDeprecationMessage(\sprintf('Since symfony/framework-bundle 8.2: The "%s" class is deprecated, use the "template" routing option instead.', self::LEGACY_TEMPLATE_CONTROLLER));

        $this->assertTrue(class_exists(self::LEGACY_TEMPLATE_CONTROLLER));
        $this->assertSame(TemplateController::class, (new \ReflectionClass(self::LEGACY_TEMPLATE_CONTROLLER))->getName());
    }

    #[RunInSeparateProcess]
    public function testAnAppGetsTheMovedServicesAsInstancesOfTheirLegacyTypes()
    {
        $kernel = new MovedClassesKernel(sys_get_temp_dir().'/'.uniqid('sf_twig_moved_classes_', true));

        try {
            $kernel->boot();

            foreach ([
                [TemplateController::class, self::LEGACY_TEMPLATE_CONTROLLER],
                ['routing.loader', 'Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader'],
            ] as [$id, $legacyClass]) {
                $service = $kernel->getContainer()->get($id);
                $this->assertTrue(is_a($service, $legacyClass), \sprintf('The "%s" service is a "%s", not a "%s".', $id, get_debug_type($service), $legacyClass));
            }
        } finally {
            (new Filesystem())->remove($kernel->getCacheDir());
        }
    }
}

class MovedClassesKernel extends Kernel
{
    public function __construct(private readonly string $cacheDir)
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
            $container->loadFromExtension('framework', [
                'secret' => '$ecret',
                'form' => ['enabled' => false],
                'router' => ['resource' => __DIR__.'/Functional/Resources/config/template_routing.yml'],
            ]);
        });
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    public function getLogDir(): string
    {
        return $this->cacheDir;
    }
}
