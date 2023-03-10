<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslatorTest extends TestCase
{
    protected $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/sf_translation';
        $this->deleteTmpDir();
    }

    protected function tearDown(): void
    {
        $this->deleteTmpDir();
    }

    protected function deleteTmpDir()
    {
        if (!file_exists($dir = $this->tmpDir)) {
            return;
        }

        $fs = new Filesystem();
        $fs->remove($dir);
    }

    public function testTransWithoutCaching()
    {
        $translator = $this->getTranslator($this->getLoader());
        $translator->setLocale('fr');
        $translator->setFallbackLocales(['en', 'es', 'pt-PT', 'pt_BR', 'fr.UTF-8', 'sr@latin']);

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->trans('choice', ['%count%' => 0]));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->trans('other choice', ['%count%' => 1]));
        $this->assertEquals('foobarbaz (fr.UTF-8)', $translator->trans('foobarbaz'));
        $this->assertEquals('foobarbax (sr@latin)', $translator->trans('foobarbax'));
    }

    public function testTransWithCaching()
    {
        // prime the cache
        $translator = $this->getTranslator($this->getLoader(), ['cache_dir' => $this->tmpDir]);
        $translator->setLocale('fr');
        $translator->setFallbackLocales(['en', 'es', 'pt-PT', 'pt_BR', 'fr.UTF-8', 'sr@latin']);

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->trans('choice', ['%count%' => 0]));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->trans('other choice', ['%count%' => 1]));
        $this->assertEquals('foobarbaz (fr.UTF-8)', $translator->trans('foobarbaz'));
        $this->assertEquals('foobarbax (sr@latin)', $translator->trans('foobarbax'));

        // do it another time as the cache is primed now
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->never())->method('load');

        $translator = $this->getTranslator($loader, ['cache_dir' => $this->tmpDir]);
        $translator->setLocale('fr');
        $translator->setFallbackLocales(['en', 'es', 'pt-PT', 'pt_BR', 'fr.UTF-8', 'sr@latin']);

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('foobarbaz (fr.UTF-8)', $translator->trans('foobarbaz'));
        $this->assertEquals('foobarbax (sr@latin)', $translator->trans('foobarbax'));
    }

    public function testTransWithCachingWithInvalidLocale()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "invalid locale" locale.');
        $loader = $this->createMock(LoaderInterface::class);
        $translator = $this->getTranslator($loader, ['cache_dir' => $this->tmpDir], 'loader', TranslatorWithInvalidLocale::class);

        $translator->trans('foo');
    }

    public function testLoadResourcesWithoutCaching()
    {
        $loader = new YamlFileLoader();
        $resourceFiles = [
            'fr' => [
                __DIR__.'/../Fixtures/Resources/translations/messages.fr.yml',
            ],
        ];

        $translator = $this->getTranslator($loader, ['resource_files' => $resourceFiles], 'yml');
        $translator->setLocale('fr');

        $this->assertEquals('répertoire', $translator->trans('folder'));
    }

    public function testGetDefaultLocale()
    {
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $translator = new Translator($container, new MessageFormatter(), 'en');

        $this->assertSame('en', $translator->getLocale());
    }

    public function testInvalidOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Translator does not support the following options: \'foo\'');
        $container = $this->createMock(ContainerInterface::class);

        new Translator($container, new MessageFormatter(), 'en', [], ['foo' => 'bar']);
    }

    /** @dataProvider getDebugModeAndCacheDirCombinations */
    public function testResourceFilesOptionLoadsBeforeOtherAddedResources($debug, $enableCache)
    {
        $someCatalogue = $this->getCatalogue('some_locale', []);

        $loader = $this->createMock(LoaderInterface::class);

        $series = [
            /* The "messages.some_locale.loader" is passed via the resource_file option and shall be loaded first */
            [['messages.some_locale.loader', 'some_locale', 'messages'], $someCatalogue],
            /* This resource is added by an addResource() call and shall be loaded after the resource_files */
            [['second_resource.some_locale.loader', 'some_locale', 'messages'], $someCatalogue],
        ];

        $loader->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(function (...$args) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            });

        $options = [
            'resource_files' => ['some_locale' => ['messages.some_locale.loader']],
            'debug' => $debug,
        ];

        if ($enableCache) {
            $options['cache_dir'] = $this->tmpDir;
        }

        /** @var Translator $translator */
        $translator = $this->createTranslator($loader, $options);
        $translator->addResource('loader', 'second_resource.some_locale.loader', 'some_locale', 'messages');

        $translator->trans('some_message', [], null, 'some_locale');
    }

    public static function getDebugModeAndCacheDirCombinations()
    {
        return [
            [false, false],
            [true, false],
            [false, true],
            [true, true],
        ];
    }

    public function testCatalogResourcesAreAddedForScannedDirectories()
    {
        $loader = new YamlFileLoader();
        $resourceFiles = [
            'fr' => [
                __DIR__.'/../Fixtures/Resources/translations/messages.fr.yml',
            ],
        ];

        /** @var Translator $translator */
        $translator = $this->getTranslator($loader, [
            'resource_files' => $resourceFiles,
            'scanned_directories' => [__DIR__, '/tmp/I/sure/hope/this/does/not/exist'],
        ], 'yml');

        $catalogue = $translator->getCatalogue('fr');

        $resources = $catalogue->getResources();

        $this->assertEquals(new DirectoryResource(__DIR__), $resources[1]);
        $this->assertEquals(new FileExistenceResource('/tmp/I/sure/hope/this/does/not/exist'), $resources[2]);
    }

    public function testCachedCatalogueIsReDumpedWhenScannedDirectoriesChange()
    {
        /** @var Translator $translator */
        $translator = $this->getTranslator(new YamlFileLoader(), [
            'cache_dir' => $this->tmpDir,
            'resource_files' => [
                'fr' => [
                    __DIR__.'/../Fixtures/Resources/translations/messages.fr.yml',
                ],
            ],
            'cache_vary' => [
                'scanned_directories' => [
                    '/Fixtures/Resources/translations/',
                ],
            ],
        ], 'yml');

        // Cached catalogue is dumped
        $this->assertSame('répertoire', $translator->trans('folder', [], 'messages', 'fr'));

        $translator = $this->getTranslator(new YamlFileLoader(), [
            'cache_dir' => $this->tmpDir,
            'resource_files' => [
                'fr' => [
                    __DIR__.'/../Fixtures/Resources/translations/messages.fr.yml',
                    __DIR__.'/../Fixtures/Resources/translations2/ccc.fr.yml',
                ],
            ],
            'cache_vary' => [
                'scanned_directories' => [
                    '/Fixtures/Resources/translations/',
                    '/Fixtures/Resources/translations2/',
                ],
            ],
        ], 'yml');

        $this->assertSame('bar', $translator->trans('foo', [], 'ccc', 'fr'));
    }

    protected function getCatalogue($locale, $messages, $resources = [])
    {
        $catalogue = new MessageCatalogue($locale);
        foreach ($messages as $key => $translation) {
            $catalogue->set($key, $translation);
        }
        foreach ($resources as $resource) {
            $catalogue->addResource($resource);
        }

        return $catalogue;
    }

    protected function getLoader()
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader
            ->expects($this->exactly(7))
            ->method('load')
            ->willReturnOnConsecutiveCalls(
                $this->getCatalogue('fr', [
                    'foo' => 'foo (FR)',
                ]),
                $this->getCatalogue('en', [
                    'foo' => 'foo (EN)',
                    'bar' => 'bar (EN)',
                    'choice' => '{0} choice 0 (EN)|{1} choice 1 (EN)|]1,Inf] choice inf (EN)',
                ]),
                $this->getCatalogue('es', [
                    'foobar' => 'foobar (ES)',
                ]),
                $this->getCatalogue('pt-PT', [
                    'foobarfoo' => 'foobarfoo (PT-PT)',
                ]),
                $this->getCatalogue('pt_BR', [
                    'other choice' => '{0} other choice 0 (PT-BR)|{1} other choice 1 (PT-BR)|]1,Inf] other choice inf (PT-BR)',
                ]),
                $this->getCatalogue('fr.UTF-8', [
                    'foobarbaz' => 'foobarbaz (fr.UTF-8)',
                ]),
                $this->getCatalogue('sr@latin', [
                    'foobarbax' => 'foobarbax (sr@latin)',
                ])
            )
        ;

        return $loader;
    }

    protected function getContainer($loader)
    {
        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->any())
            ->method('get')
            ->willReturn($loader)
        ;

        return $container;
    }

    public function getTranslator($loader, $options = [], $loaderFomat = 'loader', $translatorClass = Translator::class, $defaultLocale = 'en', array $enabledLocales = []): TranslatorInterface
    {
        $translator = $this->createTranslator($loader, $options, $translatorClass, $loaderFomat, $defaultLocale, $enabledLocales);

        if ('loader' === $loaderFomat) {
            $translator->addResource('loader', 'foo', 'fr');
            $translator->addResource('loader', 'foo', 'en');
            $translator->addResource('loader', 'foo', 'es');
            $translator->addResource('loader', 'foo', 'pt-PT'); // European Portuguese
            $translator->addResource('loader', 'foo', 'pt_BR'); // Brazilian Portuguese
            $translator->addResource('loader', 'foo', 'fr.UTF-8');
            $translator->addResource('loader', 'foo', 'sr@latin'); // Latin Serbian
        }

        return $translator;
    }

    public function testWarmup()
    {
        $loader = new YamlFileLoader();
        $resourceFiles = [
            'fr' => [
                __DIR__.'/../Fixtures/Resources/translations/messages.fr.yml',
            ],
        ];

        // prime the cache
        $translator = $this->getTranslator($loader, ['cache_dir' => $this->tmpDir, 'resource_files' => $resourceFiles], 'yml');
        $translator->setFallbackLocales(['fr']);
        $translator->warmup($this->tmpDir);

        $loader = $this->createMock(LoaderInterface::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $translator = $this->getTranslator($loader, ['cache_dir' => $this->tmpDir, 'resource_files' => $resourceFiles], 'yml');
        $translator->setLocale('fr');
        $translator->setFallbackLocales(['fr']);
        $this->assertEquals('répertoire', $translator->trans('folder'));
    }

    public function testEnabledLocales()
    {
        $loader = new YamlFileLoader();
        $resourceFiles = [
            'fr' => [
                __DIR__.'/../Fixtures/Resources/translations/messages.fr.yml',
            ],
        ];

        // prime the cache without configuring the enabled locales
        $translator = $this->getTranslator($loader, ['cache_dir' => $this->tmpDir, 'resource_files' => $resourceFiles], 'yml', Translator::class, 'en', []);
        $translator->setFallbackLocales(['fr']);
        $translator->warmup($this->tmpDir);

        $this->assertCount(2, glob($this->tmpDir.'/catalogue.*.*.php'), 'Both "en" and "fr" catalogues are generated.');

        // prime the cache and configure the enabled locales
        $this->deleteTmpDir();
        $translator = $this->getTranslator($loader, ['cache_dir' => $this->tmpDir, 'resource_files' => $resourceFiles], 'yml', Translator::class, 'en', ['fr']);
        $translator->setFallbackLocales(['fr']);
        $translator->warmup($this->tmpDir);

        $this->assertCount(1, glob($this->tmpDir.'/catalogue.*.*.php'), 'Only the "fr" catalogue is generated.');
    }

    public function testLoadingTranslationFilesWithDotsInMessageDomain()
    {
        $loader = new YamlFileLoader();
        $resourceFiles = [
            'en' => [
                __DIR__.'/../Fixtures/Resources/translations/domain.with.dots.en.yml',
            ],
        ];

        $translator = $this->getTranslator($loader, ['cache_dir' => $this->tmpDir, 'resource_files' => $resourceFiles], 'yml');
        $translator->setLocale('en');
        $translator->setFallbackLocales(['fr']);
        $this->assertEquals('It works!', $translator->trans('message', [], 'domain.with.dots'));
    }

    private function createTranslator($loader, $options, $translatorClass = Translator::class, $loaderFomat = 'loader', $defaultLocale = 'en', array $enabledLocales = [])
    {
        if (null === $defaultLocale) {
            return new $translatorClass(
                $this->getContainer($loader),
                new MessageFormatter(),
                [$loaderFomat => [$loaderFomat]],
                $options,
                $enabledLocales
            );
        }

        return new $translatorClass(
            $this->getContainer($loader),
            new MessageFormatter(),
            $defaultLocale,
            [$loaderFomat => [$loaderFomat]],
            $options,
            $enabledLocales
        );
    }
}

class TranslatorWithInvalidLocale extends Translator
{
    public function getLocale(): string
    {
        return 'invalid locale';
    }
}
