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

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Environment;

// compiled templates are classes, which stay loaded for the rest of the process
#[RunTestsInSeparateProcesses]
class FormThemesWarmupTest extends TestCase
{
    public function testWarmUpCompilesReachableFormThemes()
    {
        $container = $this->warmUp();

        $this->assertSame([
            'bootstrap_5_horizontal_layout.html.twig',
            'bootstrap_5_layout.html.twig',
            'bootstrap_base_layout.html.twig',
            'form_div_layout.html.twig',
            'form_table_layout.html.twig',
            'tailwind_2_layout.html.twig',
        ], $this->getCompiledFormThemes($container->get('test.twig')));
    }

    public function testFormThemePickedAtRuntimeIsCompiledOnFirstUse()
    {
        $container = $this->warmUp();
        $twig = $container->get('test.twig');

        $this->assertNotContains('bootstrap_4_layout.html.twig', $this->getCompiledFormThemes($twig));

        $form = $container->get('test.form.factory')->createNamed('name', TextType::class)->createView();
        $html = $twig->render('dynamic.html.twig', ['form' => $form, 'theme' => 'bootstrap_4_layout.html.twig']);

        $this->assertStringContainsString('class="form-control"', $html);
        $this->assertContains('bootstrap_4_layout.html.twig', $this->getCompiledFormThemes($twig));
    }

    protected function setUp(): void
    {
        $this->deleteTempDir();
    }

    protected function tearDown(): void
    {
        $this->deleteTempDir();
    }

    private function deleteTempDir(): void
    {
        if (file_exists($dir = sys_get_temp_dir().'/'.Kernel::VERSION.'/FormThemesWarmup')) {
            (new Filesystem())->remove($dir);
        }
    }

    private function warmUp(): ContainerInterface
    {
        $kernel = new FormThemesWarmupKernel('test', false);
        $kernel->boot();

        $container = $kernel->getContainer();
        $container->get('test.twig.template_cache_warmer')->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        return $container;
    }

    private function getCompiledFormThemes(Environment $twig): array
    {
        $formThemes = [];
        foreach (glob(\dirname((new \ReflectionClass(FormExtension::class))->getFileName(), 2).'/Resources/views/Form/*.html.twig') as $file) {
            $name = basename($file);
            if (is_file($twig->getCache(false)->generateKey($name, $twig->getTemplateClass($name)))) {
                $formThemes[] = $name;
            }
        }
        sort($formThemes);

        return $formThemes;
    }
}

class FormThemesWarmupKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container) {
            $container
                ->loadFromExtension('framework', [
                    'secret' => '$ecret',
                    'csrf_protection' => false,
                    'form' => ['enabled' => true, 'csrf_protection' => false],
                ])
                ->loadFromExtension('twig', [
                    'default_path' => __DIR__.'/templates/form_themes',
                    'form_themes' => ['bootstrap_5_horizontal_layout.html.twig'],
                ])
            ;

            $container->setAlias('test.twig', 'twig')->setPublic(true);
            $container->setAlias('test.twig.template_cache_warmer', 'twig.template_cache_warmer')->setPublic(true);
            $container->setAlias('test.form.factory', 'form.factory')->setPublic(true);
        });
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/FormThemesWarmup';
    }
}
