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
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class CachePoolClearCommandTest extends AbstractWebTestCase
{
    protected function setUp()
    {
        static::bootKernel(['test_case' => 'CachePoolClear', 'root_config' => 'config.yml']);
    }

    public function testClearPrivatePool()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['pools' => ['cache.private_pool']], ['decorated' => false]);

        $this->assertSame(0, $tester->getStatusCode(), 'cache:pool:clear exits with 0 in case of success');
        $this->assertContains('Clearing cache pool: cache.private_pool', $tester->getDisplay());
        $this->assertContains('[OK] Cache was successfully cleared.', $tester->getDisplay());
    }

    public function testClearPublicPool()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['pools' => ['cache.public_pool']], ['decorated' => false]);

        $this->assertSame(0, $tester->getStatusCode(), 'cache:pool:clear exits with 0 in case of success');
        $this->assertContains('Clearing cache pool: cache.public_pool', $tester->getDisplay());
        $this->assertContains('[OK] Cache was successfully cleared.', $tester->getDisplay());
    }

    public function testClearPoolWithCustomClearer()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['pools' => ['cache.pool_with_clearer']], ['decorated' => false]);

        $this->assertSame(0, $tester->getStatusCode(), 'cache:pool:clear exits with 0 in case of success');
        $this->assertContains('Clearing cache pool: cache.pool_with_clearer', $tester->getDisplay());
        $this->assertContains('[OK] Cache was successfully cleared.', $tester->getDisplay());
    }

    public function testCallClearer()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['pools' => ['cache.app_clearer']], ['decorated' => false]);

        $this->assertSame(0, $tester->getStatusCode(), 'cache:pool:clear exits with 0 in case of success');
        $this->assertContains('Calling cache clearer: cache.app_clearer', $tester->getDisplay());
        $this->assertContains('[OK] Cache was successfully cleared.', $tester->getDisplay());
    }

    /**
     * @expectedException        \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @expectedExceptionMessage You have requested a non-existent service "unknown_pool"
     */
    public function testClearUnexistingPool()
    {
        $this->createCommandTester()
            ->execute(['pools' => ['unknown_pool']], ['decorated' => false]);
    }

    private function createCommandTester()
    {
        $application = new Application(static::$kernel);
        $application->add(new CachePoolClearCommand(static::$container->get('cache.global_clearer')));

        return new CommandTester($application->find('cache:pool:clear'));
    }
}
