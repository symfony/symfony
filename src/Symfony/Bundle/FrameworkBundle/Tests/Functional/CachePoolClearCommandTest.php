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

use Symfony\Bundle\FrameworkBundle\Command\CachePoolClearCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @group functional
 */
class CachePoolClearCommandTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        static::bootKernel(['test_case' => 'CachePoolClear', 'root_config' => 'config.yml']);
    }

    public function testClearPrivatePool()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['pools' => ['cache.private_pool']], ['decorated' => false]);

        $tester->assertCommandIsSuccessful('cache:pool:clear exits with 0 in case of success');
        $this->assertStringContainsString('Clearing cache pool: cache.private_pool', $tester->getDisplay());
        $this->assertStringContainsString('[OK] Cache was successfully cleared.', $tester->getDisplay());
    }

    public function testClearPublicPool()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['pools' => ['cache.public_pool']], ['decorated' => false]);

        $tester->assertCommandIsSuccessful('cache:pool:clear exits with 0 in case of success');
        $this->assertStringContainsString('Clearing cache pool: cache.public_pool', $tester->getDisplay());
        $this->assertStringContainsString('[OK] Cache was successfully cleared.', $tester->getDisplay());
    }

    public function testClearPoolWithCustomClearer()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['pools' => ['cache.pool_with_clearer']], ['decorated' => false]);

        $tester->assertCommandIsSuccessful('cache:pool:clear exits with 0 in case of success');
        $this->assertStringContainsString('Clearing cache pool: cache.pool_with_clearer', $tester->getDisplay());
        $this->assertStringContainsString('[OK] Cache was successfully cleared.', $tester->getDisplay());
    }

    public function testCallClearer()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['pools' => ['cache.app_clearer']], ['decorated' => false]);

        $tester->assertCommandIsSuccessful('cache:pool:clear exits with 0 in case of success');
        $this->assertStringContainsString('Calling cache clearer: cache.app_clearer', $tester->getDisplay());
        $this->assertStringContainsString('[OK] Cache was successfully cleared.', $tester->getDisplay());
    }

    public function testClearUnexistingPool()
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('You have requested a non-existent service "unknown_pool"');
        $this->createCommandTester()
            ->execute(['pools' => ['unknown_pool']], ['decorated' => false]);
    }

    public function testClearFailed()
    {
        $tester = $this->createCommandTester();
        /** @var FilesystemAdapter $pool */
        $pool = static::getContainer()->get('cache.public_pool');
        $item = $pool->getItem('foo');
        $item->set('baz');
        $pool->save($item);
        $r = new \ReflectionObject($pool);
        $p = $r->getProperty('directory');
        $poolDir = $p->getValue($pool);

        /** @var SplFileInfo $entry */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($poolDir)) as $entry) {
            // converts files into dir to make adapter fail
            if ($entry->isFile()) {
                unlink($entry->getPathname());
                mkdir($entry->getPathname());
            }
        }

        $tester->execute(['pools' => ['cache.public_pool']]);

        $this->assertSame(1, $tester->getStatusCode(), 'cache:pool:clear exits with 1 in case of error');
        $this->assertStringNotContainsString('[OK] Cache was successfully cleared.', $tester->getDisplay());
        $this->assertStringContainsString('[WARNING] Cache pool "cache.public_pool" could not be cleared.', $tester->getDisplay());
    }

    private function createCommandTester()
    {
        $application = new Application(static::$kernel);
        $application->add(new CachePoolClearCommand(static::getContainer()->get('cache.global_clearer')));

        return new CommandTester($application->find('cache:pool:clear'));
    }
}
