<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerBundle;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class HtmlSanitizerBundleExtensionTest extends TestCase
{
    public function testHtmlSanitizer()
    {
        $container = $this->createContainerFromFile('html_sanitizer');

        // html_sanitizer service
        $this->assertSame(HtmlSanitizer::class, $container->getDefinition('html_sanitizer.sanitizer.custom')->getClass());
        $this->assertCount(1, $args = $container->getDefinition('html_sanitizer.sanitizer.custom')->getArguments());
        $this->assertSame('html_sanitizer.config.custom', (string) $args[0]);

        // config
        $this->assertTrue($container->hasDefinition('html_sanitizer.config.custom'));
        $this->assertSame(HtmlSanitizerConfig::class, $container->getDefinition('html_sanitizer.config.custom')->getClass());
        $this->assertCount(24, $calls = $container->getDefinition('html_sanitizer.config.custom')->getMethodCalls());
        $this->assertSame(
            [
                ['defaultAction', [HtmlSanitizerAction::Allow], true],
                ['allowSafeElements', [], true],
                ['allowStaticElements', [], true],
                ['allowElement', ['iframe', 'src'], true],
                ['allowElement', ['custom-tag', ['data-attr', 'data-attr-1']], true],
                ['allowElement', ['custom-tag-2', '*'], true],
                ['blockElement', ['section'], true],
                ['dropElement', ['video'], true],
                ['allowAttribute', ['src', ['iframe']], true],
                ['allowAttribute', ['data-attr', '*'], true],
                ['dropAttribute', ['data-attr', ['custom-tag']], true],
                ['dropAttribute', ['data-attr-1', []], true],
                ['dropAttribute', ['data-attr-2', '*'], true],
                ['forceAttribute', ['a', 'rel', 'noopener noreferrer'], true],
                ['forceAttribute', ['h1', 'class', 'bp4-heading'], true],
                ['forceHttpsUrls', [true], true],
                ['allowLinkSchemes', [['http', 'https', 'mailto']], true],
                ['allowLinkHosts', [['symfony.com']], true],
                ['allowRelativeLinks', [true], true],
                ['allowMediaSchemes', [['http', 'https', 'data']], true],
                ['allowMediaHosts', [['symfony.com']], true],
                ['allowRelativeMedias', [true], true],
                ['withAttributeSanitizer', ['@App\\Sanitizer\\CustomAttributeSanitizer'], true],
                ['withoutAttributeSanitizer', ['@App\\Sanitizer\\OtherCustomAttributeSanitizer'], true],
            ],

            // Convert references to their names for easier assertion
            array_map(
                static function ($call) {
                    foreach ($call[1] as $k => $arg) {
                        $call[1][$k] = $arg instanceof Reference ? '@'.$arg : $arg;
                    }

                    return $call;
                },
                $calls
            )
        );

        // Named alias
        $this->assertSame('html_sanitizer.sanitizer.all.sanitizer', (string) $container->getAlias(HtmlSanitizerInterface::class.' $allSanitizer'));
        $this->assertFalse($container->hasAlias(HtmlSanitizerInterface::class.' $default'));
    }

    public function testHtmlSanitizerDefaultNullAllowedLinkMediaHost()
    {
        $container = $this->createContainerFromFile('html_sanitizer_default_allowed_link_and_media_hosts');

        $calls = $container->getDefinition('html_sanitizer.config.custom_default')->getMethodCalls();
        $this->assertContains(['allowLinkHosts', [null], true], $calls);
        $this->assertContains(['allowRelativeLinks', [false], true], $calls);
        $this->assertContains(['allowMediaHosts', [null], true], $calls);
        $this->assertContains(['allowRelativeMedias', [false], true], $calls);
    }

    public function testHtmlSanitizerDefaultConfig()
    {
        $container = $this->createContainerFromFile('html_sanitizer_default_config');

        // html_sanitizer service
        $this->assertTrue($container->hasAlias('html_sanitizer'));
        $this->assertSame('html_sanitizer.sanitizer.default', (string) $container->getAlias('html_sanitizer'));
        $this->assertSame(HtmlSanitizer::class, $container->getDefinition('html_sanitizer.sanitizer.default')->getClass());
        $this->assertCount(1, $args = $container->getDefinition('html_sanitizer.sanitizer.default')->getArguments());
        $this->assertSame('html_sanitizer.config.default', (string) $args[0]);

        // config
        $this->assertTrue($container->hasDefinition('html_sanitizer.config.default'));
        $this->assertSame(HtmlSanitizerConfig::class, $container->getDefinition('html_sanitizer.config.default')->getClass());
        $this->assertCount(1, $calls = $container->getDefinition('html_sanitizer.config.default')->getMethodCalls());
        $this->assertSame(['allowSafeElements', [], true], $calls[0]);

        // Named alias
        $this->assertFalse($container->hasAlias(HtmlSanitizerInterface::class.' $default'));

        // Default alias
        $this->assertSame('html_sanitizer', (string) $container->getAlias(HtmlSanitizerInterface::class));
    }

    public function testDisabledSanitizerRegistersNoService()
    {
        $container = $this->createContainer();
        $container->loadFromExtension('html_sanitizer', ['enabled' => false]);
        $container->compile();

        $this->assertFalse($container->has('html_sanitizer'));
    }

    private function createContainerFromFile(string $file): ContainerBuilder
    {
        $container = $this->createContainer();
        new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures'))->load($file.'.php');
        $container->compile();

        return $container;
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->registerExtension(new HtmlSanitizerBundle()->getContainerExtension());
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        return $container;
    }
}
