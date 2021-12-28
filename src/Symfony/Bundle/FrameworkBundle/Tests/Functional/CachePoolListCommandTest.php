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

use Symfony\Bundle\FrameworkBundle\Command\CachePoolListCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class CachePoolListCommandTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        static::bootKernel(['test_case' => 'CachePools', 'root_config' => 'config.yml']);
    }

    public function testListPools()
    {
        $tester = $this->createCommandTester(['cache.app', 'cache.system']);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful('cache:pool:list exits with 0 in case of success');
        $this->assertStringContainsString('cache.app', $tester->getDisplay());
        $this->assertStringContainsString('cache.system', $tester->getDisplay());
    }

    public function testEmptyList()
    {
        $tester = $this->createCommandTester([]);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful('cache:pool:list exits with 0 in case of success');
    }

    private function createCommandTester(array $poolNames)
    {
        $application = new Application(static::$kernel);
        $application->add(new CachePoolListCommand($poolNames));

        return new CommandTester($application->find('cache:pool:list'));
    }
}
