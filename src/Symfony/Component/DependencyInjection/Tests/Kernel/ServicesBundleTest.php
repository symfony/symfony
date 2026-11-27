<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AsWorkerWarmer;
use Symfony\Component\DependencyInjection\Compiler\JsonSchemaConfigDumpPass;
use Symfony\Component\DependencyInjection\Compiler\PhpConfigReferenceDumpPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

class ServicesBundleTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/sf_services_bundle_test';
        mkdir($this->projectDir.'/config', 0o777, true);
        file_put_contents($this->projectDir.'/config/bundles.php', '<?php return ['.ServicesBundle::class.'::class => [\'all\' => true]];');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testBuildWithoutKernelParameters()
    {
        $container = new ContainerBuilder();
        new ServicesBundle()->build($container);

        foreach ($container->getCompilerPassConfig()->getPasses() as $pass) {
            $this->assertNotInstanceOf(PhpConfigReferenceDumpPass::class, $pass);
            $this->assertNotInstanceOf(JsonSchemaConfigDumpPass::class, $pass);
        }
    }

    public function testConfigReferenceAndSchemaAreDumpedInDebug()
    {
        $kernel = new ServicesBundleTestKernel('test', true, $this->projectDir);
        $kernel->boot();

        $this->assertFileExists($this->projectDir.'/config/reference.php');
        $this->assertStringContainsString('final class App', file_get_contents($this->projectDir.'/config/reference.php'));
        $this->assertFileExists($this->projectDir.'/config/schema.json');
    }

    public function testConfigReferenceAndSchemaAreNotDumpedWithoutDebug()
    {
        $kernel = new ServicesBundleTestKernel('test', false, $this->projectDir);
        $kernel->boot();

        $this->assertFileDoesNotExist($this->projectDir.'/config/reference.php');
        $this->assertFileDoesNotExist($this->projectDir.'/config/schema.json');
    }

    public function testWorkerWarmersAreAutoconfiguredAndInstantiatedOnWarmUp()
    {
        file_put_contents($this->projectDir.'/config/services.php', '<?php return static function (\\'.ContainerConfigurator::class.' $c) { $c->services()->defaults()->autoconfigure()->set('.ServicesBundleTestWorkerWarmer::class.'::class); };');
        ServicesBundleTestWorkerWarmer::$calls = [];
        $kernel = new ServicesBundleTestKernel('test', false, $this->projectDir);
        $kernel->boot();

        $warmer = $kernel->getContainer()->get('worker_warmer');
        $this->assertSame([], ServicesBundleTestWorkerWarmer::$calls);

        $warmer->warmUp();
        $this->assertSame(['__construct', '__invoke', 'connect'], ServicesBundleTestWorkerWarmer::$calls);
    }

    public function testWorkerWarmerIsRemovedWithoutWarmers()
    {
        $kernel = new ServicesBundleTestKernel('test', false, $this->projectDir);
        $kernel->boot();

        $this->assertFalse($kernel->getContainer()->has('worker_warmer'));
    }
}

#[AsWorkerWarmer]
class ServicesBundleTestWorkerWarmer
{
    public static array $calls = [];

    public function __construct()
    {
        self::$calls[] = __FUNCTION__;
    }

    public function __invoke(): void
    {
        self::$calls[] = __FUNCTION__;
    }

    #[AsWorkerWarmer]
    public function connect(): void
    {
        self::$calls[] = __FUNCTION__;
    }
}

class ServicesBundleTestKernel extends AbstractKernel
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
}
