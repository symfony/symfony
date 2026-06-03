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

use Symfony\Component\HttpKernel\KernelInterface;

class KernelTestCaseFreshCacheTest extends AbstractWebTestCase
{
    private static string $trackedFile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$trackedFile = sys_get_temp_dir().'/'.static::getVarDir().'/tracked_file.yaml';
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        @unlink(self::$trackedFile);
    }

    public function testContainerIsRebuiltWhenTrackedFileAppears()
    {
        @unlink(self::$trackedFile);

        // First boot: container is built tracking the file as non-existing
        static::bootKernel(['test_case' => 'TestServiceContainer', 'debug' => true]);

        $this->assertFalse(static::$kernel->getContainer()->getParameter('tracked_file_existed'));

        static::ensureKernelShutdown();

        // Create the tracked file between boots
        @mkdir(\dirname(self::$trackedFile), 0o777, true);
        file_put_contents(self::$trackedFile, 'placeholder');

        // Reboot: should detect the resource change and rebuild the container
        static::bootKernel(['test_case' => 'TestServiceContainer', 'debug' => true]);

        $this->assertTrue(static::$kernel->getContainer()->getParameter('tracked_file_existed'));
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        $kernel = parent::createKernel($options);

        FileExistenceTrackingKernel::$trackedFile = self::$trackedFile;

        return $kernel;
    }

    protected static function getKernelClass(): string
    {
        require_once __DIR__.'/app/AppKernel.php';

        return FileExistenceTrackingKernel::class;
    }
}

class FileExistenceTrackingKernel extends app\AppKernel
{
    public static string $trackedFile;

    protected function build(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        parent::build($container);

        $container->setParameter('tracked_file_existed', $container->fileExists(self::$trackedFile));
    }
}
