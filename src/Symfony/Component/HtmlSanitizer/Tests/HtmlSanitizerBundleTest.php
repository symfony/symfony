<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerBundle;

class HtmlSanitizerBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_html_sanitizer_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testSanitizersAreRegistered()
    {
        $kernel = new TestHtmlSanitizerKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $sanitizer = $container->get('test.html_sanitizer');
        $this->assertInstanceOf(HtmlSanitizer::class, $sanitizer);
        $this->assertSame('<div>Hello</div>', $sanitizer->sanitize('<div onclick="alert(1)">Hello</div>'));

        $custom = $container->get('test.html_sanitizer.custom');
        $this->assertInstanceOf(HtmlSanitizer::class, $custom);
        $this->assertSame('Hello', $custom->sanitize('<div>Hello</div>'));
    }
}

class TestHtmlSanitizerKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new HtmlSanitizerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('html_sanitizer', [
            'sanitizers' => [
                'custom' => [
                    'default_action' => 'block',
                ],
            ],
        ]);
        $container->services()->alias('test.html_sanitizer', 'html_sanitizer')->public();
        $container->services()->alias('test.html_sanitizer.custom', 'html_sanitizer.sanitizer.custom')->public();
    }
}
