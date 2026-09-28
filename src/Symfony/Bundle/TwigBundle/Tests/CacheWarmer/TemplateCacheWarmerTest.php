<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\CacheWarmer;

use Symfony\Bundle\TwigBundle\CacheWarmer\TemplateCacheWarmer;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Source;

class TemplateCacheWarmerTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/'.uniqid('twig_template_cache_warmer_', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDir);
    }

    public function testWarmUpCompilesTemplatesListedUnderSeveralNamesOnce()
    {
        $loader = new class extends FilesystemLoader {
            public array $compiledTemplates = [];

            public function getSourceContext(string $name): Source
            {
                $this->compiledTemplates[] = $name;

                return parent::getSourceContext($name);
            }
        };
        $loader->addPath(__DIR__.'/../Fixtures/templates/CacheWarmer/override', 'Foo');
        $loader->addPath(__DIR__.'/../Fixtures/templates/CacheWarmer/bundle', 'Foo');
        $loader->addPath(__DIR__.'/../Fixtures/templates/CacheWarmer/bundle', '!Foo');
        $twig = new Environment($loader, ['cache' => $this->cacheDir]);

        $warmer = new TemplateCacheWarmer(new ServiceLocator(['twig' => static fn () => $twig]), [
            '@Foo/invalid.html.twig',
            '@!Foo/invalid.html.twig',
            '@Foo/valid.html.twig',
            '@!Foo/valid.html.twig',
            '@Foo/overridden.html.twig',
            '@!Foo/overridden.html.twig',
        ]);
        $warmer->warmUp($this->cacheDir);

        $this->assertSame(['@Foo/invalid.html.twig', '@Foo/valid.html.twig', '@Foo/overridden.html.twig', '@!Foo/overridden.html.twig'], $loader->compiledTemplates);
        $this->assertCount(3, glob($this->cacheDir.'/*/*.php'));
    }
}
