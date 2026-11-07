<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness\ProbeController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class ConfigCacheFreshnessTest extends AbstractWebTestCase
{
    #[DataProvider('provideMutations')]
    public function testChangedConfigurationIsRebuiltAndThenReused(string $mode)
    {
        $directory = sys_get_temp_dir().'/'.self::getVarDir().'/'.$mode;
        $filesystem = new Filesystem();
        $filesystem->mkdir($directory.'/config');
        $filesystem->dumpFile($directory.'/config/services.yml', Yaml::dump([
            'framework' => ['secret' => 'test', 'router' => ['resource' => $directory.'/config/routes.yml'], 'http_method_override' => false],
            'parameters' => ['freshness.value' => 'v1'],
            'services' => [ProbeController::class => ['public' => true, 'arguments' => ['%freshness.value%']]],
        ], 5));
        $filesystem->dumpFile($directory.'/config/routes.yml', Yaml::dump([
            'probe' => ['path' => '/', 'controller' => ProbeController::class],
        ], 3));
        $initialMtime = time() - ('recent' === $mode || 'post-write' === $mode ? 1 : 100);
        touch($directory.'/config/services.yml', $initialMtime);

        try {
            $first = $this->request($directory, $mode);
            if ('post-write' === $mode) {
                $filesystem->dumpFile($directory.'/config/services.yml', str_replace('v1', 'v2', file_get_contents($directory.'/config/services.yml')));
                touch($directory.'/config/services.yml', $first['cache_mtime']);
            }
            $second = $this->request($directory, $mode);
            $third = $this->request($directory, $mode);
            $observations = json_encode([$first, $second, $third], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);

            self::assertSame('v1', $first['response'], $observations);
            self::assertSame(1, $first['builds'], $observations);
            self::assertSame('v2', $second['response'], $observations);
            self::assertSame('v2', $second['parameter'], $observations);
            self::assertSame(2, $second['builds'], $observations);
            self::assertSame('v2', $third['response'], $observations);
            self::assertSame('v2', $third['parameter'], $observations);
            self::assertSame(2, $third['builds'], $observations);
            self::assertCount(3, array_unique(array_column([$first, $second, $third], 'pid')), $observations);
            self::assertTrue($first['debug']);
            self::assertSame($first['cache'], $second['cache']);
            self::assertSame($second['cache'], $third['cache']);
            self::assertFileExists($directory.'/config/reference.php');
            self::assertFileExists($directory.'/config/schema.json');

            if ('post-write' !== $mode) {
                $mutation = json_decode(file_get_contents($directory.'/mutation.json'), true, flags: \JSON_THROW_ON_ERROR);
                self::assertLessThan($first['cache_mtime'], $mutation['after'], $observations);
                if ('recent' === $mode) {
                    self::assertSame($mutation['before'], $mutation['after']);
                    self::assertLessThanOrEqual(1, $mutation['captured_at'] - $mutation['before']);
                } else {
                    self::assertSame($initialMtime, $mutation['before']);
                    self::assertLessThan($mutation['after'], $mutation['before']);
                }
            }
        } finally {
            $filesystem->remove($directory);
        }
    }

    public static function provideMutations(): iterable
    {
        yield 'after YAML parsing, before resource registration' => ['read'];
        yield 'during compilation, after resource registration' => ['compiler'];
        yield 'same capture second, later cache write' => ['recent'];
        yield 'after cache write, same cache second' => ['post-write'];
    }

    #[DataProvider('provideCacheFamilies')]
    public function testApplicationCachesRecoverAndSettle(string $mode, string $observation, string $expected)
    {
        $missedWindows = [];
        for ($attempt = 0; $attempt < ('route-directory-equal' === $mode ? 5 : 1); ++$attempt) {
            if (null === $missedWindow = $this->runCacheScenario($mode, $observation, $expected)) {
                return;
            }
            $missedWindows[] = $missedWindow;
        }

        self::fail('Could not establish the equal-timestamp prerequisite in five independent application builds: '.implode("\n", $missedWindows));
    }

    public static function provideCacheFamilies(): iterable
    {
        yield 'YAML routes' => ['route-yaml', 'generated', '/v2'];
        yield 'PHP routes' => ['route-php', 'generated', '/v2'];
        yield 'object route callback' => ['route-object', 'generated', '/v2'];
        yield 'shared matcher and generator collection' => ['route-between-dumps', 'generated', '/v2'];
        yield 'directory routes' => ['route-directory', 'generated', '/v2'];
        yield 'route added during directory discovery' => ['route-directory-add', 'generated', '/v2'];
        yield 'route added after an old directory snapshot' => ['route-directory-add-later', 'generated', '/v2'];
        yield 'route removed during directory consumption' => ['route-directory-remove', 'generated', '/v2'];
        yield 'unchanged recent route directory' => ['route-directory-unchanged', 'generated', '/v1'];
        yield 'unchanged directory and normal route cache share a timestamp' => ['route-directory-equal', 'generated', '/v1'];
        yield 'actual container and router snapshots in one checker' => ['route-checker', 'generated', '/v2'];
        yield 'YAML translation' => ['translation-yml', 'fallback', 'v2'];
        yield 'XLIFF translation' => ['translation-xliff', 'fallback', 'v2'];
        yield 'Qt translation' => ['translation-qt', 'fallback', 'v2'];
        yield 'ICU DAT translation' => ['translation-dat', 'fallback', 'Symfony 2 is super'];
        yield 'ICU RES translation' => ['translation-res', 'fallback', 'Symfony 2 is super'];
        yield 'translation file discovery' => ['translation-discovery', 'fallback', 'v2'];
        yield 'translation added during catalogue loading' => ['translation-directory-add', 'added_message', 'v2'];
        yield 'translation removed during catalogue loading' => ['translation-directory-remove', 'removed_message', 'key'];
        yield 'unchanged recent translation directory' => ['translation-directory-unchanged', 'fallback', 'v1'];
        yield 'service glob discovery' => ['glob', 'added', 'v2'];
        yield 'reflection before resource construction' => ['reflection', 'injected', 'v2'];
        yield 'reflection after resource registration' => ['reflection-registered', 'injected', 'v2'];
        yield 'ordinary mapped asset edit' => ['asset', 'asset', 'body { --version: v2; }'];
        yield 'generated reference and schema changes' => ['generated', 'response', 'v2'];
        yield 'literal percent signs in translation filenames' => ['percent', 'message', 'Literal'];
    }

    /**
     * Runs a complete application scenario, reporting only a missed initial timestamp window before either reuse request.
     */
    private function runCacheScenario(string $mode, string $observation, string $expected): ?string
    {
        if (\in_array($mode, ['translation-dat', 'translation-res'], true) && !class_exists(\ResourceBundle::class)) {
            self::markTestSkipped('The intl extension is required for ICU resource bundles.');
        }
        $directory = sys_get_temp_dir().'/'.self::getVarDir().'/'.$mode;
        $filesystem = new Filesystem();
        $filesystem->mkdir([$directory.'/config', $directory.'/classes', $directory.'/translations', $directory.'/inputs', $directory.'/assets']);
        $configuration = [
            'framework' => ['secret' => 'test', 'router' => ['resource' => $directory.'/config/routes.yml'], 'http_method_override' => false],
            'parameters' => ['freshness.value' => 'v1', 'freshness.v1' => 'v1', 'freshness.v2' => 'v2'],
            'services' => [ProbeController::class => ['public' => true, 'arguments' => ['%freshness.value%']]],
        ];
        $routes = $this->configureRouteInputs($mode, $directory, $filesystem, $configuration);
        $this->configureTranslationInputs($mode, $directory, $filesystem, $configuration);
        if ('glob' === $mode) {
            $filesystem->dumpFile($directory.'/classes/First.php', '<?php namespace FreshnessFixture; class First {}');
            $configuration['services']['FreshnessFixture\\'] = ['resource' => $directory.'/classes/*', 'public' => true];
        } elseif (str_starts_with($mode, 'reflection')) {
            $filesystem->dumpFile($directory.'/classes/Service.php', <<<'PHP'
                <?php
                namespace FreshnessFixture;
                use Symfony\Component\DependencyInjection\Attribute\Autowire;
                class Service {
                    public function __construct(#[Autowire('%freshness.v1%')] public string $value) {}
                }
                PHP);
            touch($directory.'/classes/Service.php', time() - 100);
            $configuration['services']['FreshnessFixture\\Service'] = ['autowire' => true, 'public' => true];
        } elseif ('asset' === $mode) {
            $configuration['framework']['asset_mapper'] = ['paths' => [$directory.'/assets']];
            $filesystem->dumpFile($directory.'/assets/app.css', 'body { --version: v1; }');
        } elseif ('generated' === $mode) {
            unset($configuration['parameters']['freshness.value']);
            $configuration['foo'] = [];
            $filesystem->dumpFile($directory.'/classes/GeneratedKernel.php', <<<'PHP'
                <?php
                namespace FreshnessFixture;
                use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness\FreshnessKernel;
                use Symfony\Component\Config\Definition\Builder\TreeBuilder;
                class GeneratedKernel extends FreshnessKernel {
                    /** @return array{'freshness.value': string, ...<string, mixed>} */
                    protected function getKernelParameters(): array {
                        return ['freshness.value' => 'v1'] + parent::getKernelParameters();
                    }
                    public function getConfigTreeBuilder(): TreeBuilder {
                        $builder = new TreeBuilder('foo');
                        $builder->getRootNode()->children()->scalarNode('foo')->defaultValue('v1');
                        return $builder;
                    }
                }
                PHP);
        }
        $filesystem->dumpFile($directory.'/config/routes.yml', Yaml::dump($routes, 4));
        $filesystem->dumpFile($directory.'/config/services.yml', Yaml::dump($configuration, 6));

        try {
            $first = $this->request($directory, $mode);
            if ('route-directory-equal' === $mode && $first['directory_mtime'] !== $first['route_cache_mtime']) {
                return json_encode($first, \JSON_THROW_ON_ERROR);
            }
            if ('asset' === $mode) {
                $filesystem->dumpFile($directory.'/assets/app.css', 'body { --version: v2; }');
            } elseif ('generated' === $mode) {
                $path = $directory.'/classes/GeneratedKernel.php';
                $filesystem->dumpFile($path, str_replace('v1', 'v2', file_get_contents($path)));
                touch($path, $first['cache_mtime']);
            }
            $second = $this->request($directory, $mode);
            $third = $this->request($directory, $mode);
            $observations = json_encode([$first, $second, $third], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
            self::assertSame($expected, $second[$observation], $observations);
            self::assertSame($expected, $third[$observation], $observations);
            self::assertSame($second['builds'], $third['builds'], $observations);
            self::assertLessThanOrEqual(2, $second['builds'], $observations);
            self::assertSame($second['dumps'], $third['dumps'], $observations);
            if (\in_array($mode, ['route-directory-unchanged', 'route-directory-equal', 'translation-directory-unchanged'], true)) {
                self::assertSame($first['builds'], $second['builds'], $observations);
                self::assertSame($first['dumps'], $second['dumps'], $observations);
            }
            if ('route-directory-equal' === $mode) {
                self::assertSame($first['directory_mtime'], $first['route_cache_mtime'], $observations);
            }
            foreach ($second['dumps'] as $count) {
                self::assertLessThanOrEqual(2, $count, $observations);
            }
            self::assertCount(3, array_unique(array_column([$first, $second, $third], 'pid')));
            if (str_starts_with($mode, 'route-')) {
                self::assertSame('route-checker' === $mode ? '/v2' : '/v1', $first['generated'], $observations);
                self::assertSame('dynamic', $third['matched']);
                self::assertNotEmpty($second['dumps']);
            }
            if ('route-checker' === $mode) {
                self::assertSame([true, false], $first['checks']['verdicts'], $observations);
                self::assertSame([true, false], $first['checks']['direct'], $observations);
                self::assertSame(2, $first['checks']['calls']);
                self::assertSame($first['checks']['identities'][0], $first['checks']['identities'][1]);
                self::assertNotSame($first['checks']['snapshots'][0], $first['checks']['snapshots'][1]);
                self::assertSame($first['pid'], $first['checks']['pid']);
            }
            if (str_starts_with($mode, 'translation-')) {
                self::assertSame('translation-discovery' === $mode || str_starts_with($mode, 'translation-directory') ? 'v1' : $expected, $third['message']);
                self::assertNotEmpty($second['dumps']);
                if (\in_array($mode, ['translation-yml', 'translation-xliff', 'translation-qt', 'translation-dat', 'translation-res'], true)) {
                    $mutation = json_decode(file_get_contents($directory.'/translation-mutation.json'), true, flags: \JSON_THROW_ON_ERROR);
                    self::assertGreaterThan($mutation['before'], $mutation['after']);
                    self::assertSame('translation-dat' === $mode || 'translation-res' === $mode ? 'Symfony 2 is great' : 'v1', $first['message'], $observations);
                    self::assertSame($first['message'], $first['fallback'], $observations);
                    self::assertSame([1, 1, 1], array_column([$first, $second, $third], 'builds'), $observations);
                    self::assertNotEmpty($first['catalogue_mtimes']);
                    self::assertSame(array_keys($first['catalogue_mtimes']), array_keys($second['catalogue_mtimes']));
                    foreach ($first['catalogue_mtimes'] as $path => $mtime) {
                        self::assertLessThan($mtime, $mutation['after'], $observations);
                        self::assertSame([1, 2, 2], array_map(static fn ($request) => $request['dumps'][$path], [$first, $second, $third]), $observations);
                    }
                }
            }
            if ('generated' === $mode) {
                self::assertNotSame($first['generated_hashes']['reference'], $second['generated_hashes']['reference']);
                self::assertNotSame($first['generated_hashes']['schema'], $second['generated_hashes']['schema']);
                self::assertSame($second['generated_hashes'], $third['generated_hashes']);
            }
            if (str_starts_with($mode, 'reflection')) {
                self::assertSame('v1', $first['injected'], $observations);
                self::assertGreaterThanOrEqual($first['request_start'], $first['reflection_source_mtime'], $observations);
                self::assertLessThan($first['cache_mtime'], $first['reflection_source_mtime'], $observations);
            }

            return null;
        } finally {
            $filesystem->remove($directory);
        }
    }

    /**
     * Prepares route inputs and their normal loader configuration for the selected mutation.
     *
     * @param array{framework: array<string, mixed>, parameters: array<string, string>, services: array<string, mixed>} $configuration
     *
     * @return array<string, array<string, mixed>>
     */
    private function configureRouteInputs(string $mode, string $directory, Filesystem $filesystem, array &$configuration): array
    {
        $routes = ['probe' => ['path' => '/', 'controller' => ProbeController::class], 'dynamic' => ['path' => '/v1']];
        if (str_starts_with($mode, 'route-directory')) {
            $filesystem->mkdir($directory.'/config/routes');
            $filesystem->dumpFile($directory.'/config/routes/dynamic.yml', Yaml::dump(['dynamic' => ['path' => '/v1']], 3));
            $routes = ['probe' => $routes['probe'], 'directory' => ['resource' => 'routes/', 'type' => 'directory']];
            if ('route-directory-remove' === $mode) {
                $routes = ['probe' => $routes['probe'], 'dynamic' => ['path' => '/v2'], 'directory' => $routes['directory']];
            }
            if ('route-directory-add-later' === $mode) {
                touch($directory.'/config/routes/dynamic.yml', time() - 100);
                touch($directory.'/config/routes', time() - 100);
            } else {
                touch($directory.'/config/routes/dynamic.yml', time() - 1);
                touch($directory.'/config/routes', time() - 1);
            }
        } elseif ('route-php' === $mode) {
            $configuration['framework']['router']['resource'] = $directory.'/config/routes.php';
            $filesystem->dumpFile($directory.'/config/routes.php', <<<'PHP'
                <?php
                use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness\Mutation;
                use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness\ProbeController;
                use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
                return static function (RoutingConfigurator $routes): void {
                    $routes->add('probe', '/')->controller(ProbeController::class);
                    $routes->add('dynamic', '/v1');
                    Mutation::change(__FILE__);
                };
                PHP);
        } elseif ('route-object' === $mode) {
            $routes = ['object' => ['resource' => 'fixture.routes', 'type' => 'service']];
            $configuration['services']['fixture.routes'] = ['class' => 'FreshnessFixture\\Routes', 'tags' => ['routing.route_loader']];
            $filesystem->dumpFile($directory.'/classes/Routes.php', <<<'PHP'
                <?php
                namespace FreshnessFixture;
                use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness\Mutation;
                use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness\ProbeController;
                use Symfony\Component\Routing\Route;
                use Symfony\Component\Routing\RouteCollection;
                class Routes {
                    public function __invoke(): RouteCollection {
                        $routes = new RouteCollection();
                        $routes->add('probe', new Route('/', ['_controller' => ProbeController::class]));
                        $routes->add('dynamic', new Route('/v1'));
                        Mutation::change(__FILE__);
                        return $routes;
                    }
                }
                PHP);
        }

        return $routes;
    }

    /**
     * Keeps file-capture inputs outside discovered translation directories so directory tracking cannot hide a late capture.
     *
     * @param array{framework: array<string, mixed>, parameters: array<string, string>, services: array<string, mixed>} $configuration
     */
    private function configureTranslationInputs(string $mode, string $directory, Filesystem $filesystem, array &$configuration): void
    {
        if (str_starts_with($mode, 'translation-')) {
            $configuration['framework']['translator'] = ['paths' => [$directory.'/translations'], 'fallbacks' => ['en']];
            $configuration['framework']['default_locale'] = 'en';
            $format = substr($mode, \strlen('translation-'));
            $fileCapture = \in_array($format, ['yml', 'xliff', 'qt', 'dat', 'res'], true);
            if ($fileCapture) {
                $configuration['framework']['translator'] = ['paths' => [], 'default_path' => null, 'fallbacks' => ['en']];
            }
            if ('dat' === $format || 'res' === $format) {
                $fixture = __DIR__.'/app/ConfigCacheFreshness/translations/resourcebundle/before/'.$format;
                if ('dat' === $format) {
                    $filesystem->copy($fixture.'/resources.dat', $directory.'/inputs/resources.dat');
                } else {
                    $filesystem->mkdir($directory.'/inputs/res');
                    $filesystem->copy($fixture.'/en.res', $directory.'/inputs/res/en.res');
                }
            } else {
                [$extension, $contents] = match ($format) {
                    'xliff' => ['xlf', '<?xml version="1.0"?><xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2"><file source-language="en" datatype="plaintext" original="probe"><body><trans-unit id="key"><source>key</source><target>v1</target></trans-unit></body></file></xliff>'],
                    'qt' => ['ts', '<?xml version="1.0"?><TS><context><name>probe</name><message><source>key</source><translation>v1</translation></message></context></TS>'],
                    default => ['yaml', 'key: v1'],
                };
                $filesystem->dumpFile($fileCapture ? $directory.'/inputs/probe.'.$extension : $directory.'/translations/probe.en.'.$extension, $contents);
            }
            if ($fileCapture) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory.'/inputs', \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $input) {
                    touch($input->getPathname(), time() - 100);
                }
                touch($directory.'/inputs', time() - 100);
            }
        } elseif ('percent' === $mode) {
            $configuration['framework']['translator'] = ['paths' => [$directory.'/translations']];
            $filesystem->dumpFile($directory.'/translations/some%missing%.en.yaml', 'key: Literal');
        }
        if ('translation-directory-remove' === $mode) {
            $configuration['framework']['enabled_locales'] = ['en'];
            $filesystem->dumpFile($directory.'/translations/removed.fr.yaml', 'key: v1');
        }
    }

    /**
     * Runs one real application lifecycle in a new PHP process against the scenario's shared cache.
     *
     * @return array{pid: int, response: string, parameter: string, builds: int, debug: bool, cache: string, cache_mtime: int, source_mtime: int, dumps: array<string, int>, catalogue_mtimes?: array<string, int>, generated?: string, matched?: string, message?: string, fallback?: string, added_message?: string, removed_message?: string, added?: string|null, injected?: string, reflection_source_mtime?: int, request_start?: int, directory_mtime?: int, route_cache_mtime?: int, asset?: string, generated_hashes?: array{reference: string, schema: string}, checks?: array{pid: int, calls: int, timestamp: int, direct: list<bool>, verdicts: list<bool>, identities: list<string>, snapshots: list<string>}}
     */
    private function request(string $directory, string $mode): array
    {
        foreach (ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if ($loader->findFile(ProbeController::class)) {
                $autoload = $vendor.'/autoload.php';
                break;
            }
        }
        $process = new Process([\PHP_BINARY, '-d', 'opcache.enable_cli=0', __DIR__.'/app/ConfigCacheFreshness/run.php', $autoload, $directory, $mode]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
