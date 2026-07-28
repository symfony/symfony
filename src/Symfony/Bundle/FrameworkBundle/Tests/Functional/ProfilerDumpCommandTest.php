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

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('functional')]
class ProfilerDumpCommandTest extends AbstractWebTestCase
{
    public function testDumpProfile()
    {
        $client = $this->createClient(['test_case' => 'Profiler', 'root_config' => 'config.yml']);

        $client->enableProfiler();
        $client->request('GET', '/profiler');
        $token = $client->getProfile()->getToken();

        $application = new Application(static::$kernel);
        $tester = new CommandTester($application->find('profiler:dump'));
        $tester->execute(['token' => $token], ['decorated' => false]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('# Profile '.$token, $tester->getDisplay());
        $this->assertStringContainsString('## request', $tester->getDisplay());
    }

    public function testCommandIsUnregisteredWhenProfilerIsDisabled()
    {
        static::bootKernel(['test_case' => 'ContainerDebug', 'root_config' => 'config.yml']);

        $application = new Application(static::$kernel);

        $this->assertFalse($application->has('profiler:dump'));
    }
}
