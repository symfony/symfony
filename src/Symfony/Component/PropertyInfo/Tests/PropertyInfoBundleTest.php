<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests;

use phpDocumentor\Reflection\Types\ContextFactory;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyInfo\PropertyInfoBundle;
use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\TypeInfo\Type;

class PropertyInfoBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_property_info_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheExtractorIsRegisteredWithoutAnyCachePool()
    {
        $kernel = new TestPropertyInfoKernel('test', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertFalse($container->has('cache.property_info'));

        $propertyInfo = $container->get('test.property_info');
        $this->assertInstanceOf(PropertyInfoExtractor::class, $propertyInfo);
        $this->assertEquals(Type::string(), $propertyInfo->getType(PropertyInfoBundleSubject::class, 'foo'));
    }

    public function testTheCachePoolIsRegisteredAlongsideTheSystemPool()
    {
        $kernel = new TestPropertyInfoKernel('cached', false, $this->varDir, true);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertTrue($container->has('test.cache'));

        $propertyInfo = $container->get('test.property_info');
        $this->assertInstanceOf(PropertyInfoCacheExtractor::class, $propertyInfo);
        $this->assertEquals(Type::string(), $propertyInfo->getType(PropertyInfoBundleSubject::class, 'foo'));
    }

    public function testThePropertyInfoOfTheGivenClassesIsReadFromTheBuild()
    {
        $kernel = new TestPropertyInfoKernel('cached', false, $this->varDir, true);
        $kernel->boot();
        $container = $kernel->getContainer();

        $container->get('test.cache_warmer')->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());
        $this->assertFileExists($kernel->getBuildDir().'/property_info.php');

        $propertyInfo = $container->get('test.property_info');
        $this->assertEquals(Type::string(), $propertyInfo->getType(PropertyInfoBundleSubject::class, 'foo'));
        $this->assertSame([], $container->get('test.cache')->getValues());
    }

    public function testTaggedExtractorsAreCollectedThroughAutoconfiguration()
    {
        $kernel = new TestPropertyInfoKernel('test', false, $this->varDir);
        $kernel->boot();

        $this->assertSame(['foo', 'bar'], $kernel->getContainer()->get('test.property_info')->getProperties(PropertyInfoBundleSubject::class));
    }

    public function testRefinementsAreResolvedAccordingToTheTypeInfoConfiguration()
    {
        if (!class_exists(PhpDocParser::class) || !class_exists(ContextFactory::class)) {
            $this->markTestSkipped('"phpstan/phpdoc-parser" and "phpdocumentor/type-resolver" dependencies are required.');
        }

        $kernel = new TestPropertyInfoKernel('test', false, $this->varDir);
        $kernel->boot();

        $this->assertEquals(Type::intRange(1, null), $kernel->getContainer()->get('test.property_info')->getType(PropertyInfoBundleSubject::class, 'count'));
    }

    public function testOnlyTheConfiguredRefinementsAreResolved()
    {
        if (!class_exists(PhpDocParser::class) || !class_exists(ContextFactory::class)) {
            $this->markTestSkipped('"phpstan/phpdoc-parser" and "phpdocumentor/type-resolver" dependencies are required.');
        }

        $kernel = new TestPropertyInfoKernel('literals', false, $this->varDir, refinements: ['literal']);
        $kernel->boot();
        $propertyInfo = $kernel->getContainer()->get('test.property_info');

        $this->assertEquals(Type::int(), $propertyInfo->getType(PropertyInfoBundleSubject::class, 'count'));
        $this->assertEquals(Type::union(Type::literal('a'), Type::literal('b')), $propertyInfo->getType(PropertyInfoBundleSubject::class, 'letter'));
    }

    public function testTypeAliasesAreResolved()
    {
        if (!class_exists(PhpDocParser::class) || !class_exists(ContextFactory::class)) {
            $this->markTestSkipped('"phpstan/phpdoc-parser" and "phpdocumentor/type-resolver" dependencies are required.');
        }

        $kernel = new TestPropertyInfoKernel('test', false, $this->varDir);
        $kernel->boot();

        $this->assertEquals(Type::int(), $kernel->getContainer()->get('test.property_info')->getType(PropertyInfoBundleSubject::class, 'aliased'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testNotSettingTheRefinementsOptionIsDeprecatedOnce()
    {
        if (!class_exists(PhpDocParser::class) || !class_exists(ContextFactory::class)) {
            $this->markTestSkipped('"phpstan/phpdoc-parser" and "phpdocumentor/type-resolver" dependencies are required.');
        }

        $deprecations = [];
        set_error_handler(static function (int $type, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $kernel = new TestPropertyInfoKernel('unset', false, $this->varDir, refinements: null);
            $kernel->boot();
            $type = $kernel->getContainer()->get('test.property_info')->getType(PropertyInfoBundleSubject::class, 'count');
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['Since symfony/type-info 8.2: Not setting the "type_info.refinements" option is deprecated; all PHPDoc refinements will be resolved by default in 9.0. Set it to true to resolve them now, or to false to keep their builtin types.'], array_values(array_filter($deprecations, static fn (string $message): bool => str_contains($message, 'refinements'))));
        $this->assertEquals(Type::int(), $type);
    }

    public function testNoRefinementsAreResolvedWithoutDeprecationWhenTypeInfoIsDisabled()
    {
        if (!class_exists(PhpDocParser::class) || !class_exists(ContextFactory::class)) {
            $this->markTestSkipped('"phpstan/phpdoc-parser" and "phpdocumentor/type-resolver" dependencies are required.');
        }

        $kernel = new TestPropertyInfoKernel('no_type_info', false, $this->varDir, withTypeInfo: false);
        $kernel->boot();

        $this->assertEquals(Type::int(), $kernel->getContainer()->get('test.property_info')->getType(PropertyInfoBundleSubject::class, 'count'));
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder();
        new PropertyInfoBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertFalse($container->hasDefinition('property_info'));
        $this->assertFalse($container->hasDefinition('property_info.reflection_extractor'));
        $this->assertFalse($container->hasDefinition('cache.property_info'));
    }
}

class PropertyInfoBundleSubject
{
    public string $foo = 'bar';

    /**
     * @var positive-int
     */
    public int $count = 1;

    /**
     * @var 'a'|'b'
     */
    public string $letter = 'a';

    /**
     * @var PropertyInfoBundleAlias
     */
    public $aliased;
}

class PropertyInfoBundleListExtractor implements PropertyListExtractorInterface
{
    public function getProperties(string $class, array $context = []): ?array
    {
        return PropertyInfoBundleSubject::class === $class ? ['foo', 'bar'] : null;
    }
}

class TestPropertyInfoKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir, private bool $withCacheSystem = false, private array|bool|null $refinements = true, private bool $withTypeInfo = true)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new PropertyInfoBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('type_info', $this->withTypeInfo ? ['aliases' => ['PropertyInfoBundleAlias' => 'int']] + (null !== $this->refinements ? ['refinements' => $this->refinements] : []) : ['enabled' => false]);

        $services = $container->services();
        $services
            ->set(PropertyInfoBundleListExtractor::class)->autoconfigure()
            ->alias('test.property_info', 'property_info')->public()
        ;

        if ($this->withCacheSystem) {
            $services
                ->set('cache.system', ArrayAdapter::class)
                ->alias('test.cache', 'cache.property_info')->public()
                ->alias('test.cache_warmer', 'property_info.cache_warmer')->public()
            ;
        }
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasDefinition('property_info.cache_warmer')) {
                    $container->getDefinition('property_info.cache_warmer')->replaceArgument(1, [PropertyInfoBundleSubject::class]);
                }
            }
        });
    }
}
