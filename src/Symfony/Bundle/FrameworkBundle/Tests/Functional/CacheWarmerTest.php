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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;

class CacheWarmerTest extends WebTestCase
{
    /**
     * @var Application
     */
    private $application;

    protected function setUp()
    {
        $kernel = static::createKernel(array('test_case' => 'CacheWarmer', 'root_config' => 'config.yml'));
        $this->application = new Application($kernel);
        $this->application->doRun(new ArrayInput(array()), new NullOutput());
    }

    public function testCacheWarmer()
    {
        $tester = $this->createCommandTester();
        $tester->execute(array());

        $cacheDirectory = $this->application->getKernel()->getCacheDir().'/cache_warmer';
        $this->assertTrue(file_exists($cacheDirectory), 'Cache directory does not exist.');
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester()
    {
        $command = $this->application->find('cache:warmup');

        return new CommandTester($command);
    }
}
