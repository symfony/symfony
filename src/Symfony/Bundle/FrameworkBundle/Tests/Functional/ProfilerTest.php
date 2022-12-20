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

class ProfilerTest extends AbstractWebTestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testProfilerIsDisabled($insulate)
    {
        $client = self::createClient(['test_case' => 'Profiler', 'root_config' => 'config.yml']);
        if ($insulate) {
            $client->insulate();
        }

        $client->request('GET', '/profiler');
        self::assertNull($client->getProfile());

        // enable the profiler for the next request
        $client->enableProfiler();
        self::assertNull($client->getProfile());
        $client->request('GET', '/profiler');
        self::assertIsObject($client->getProfile());

        $client->request('GET', '/profiler');
        self::assertNull($client->getProfile());
    }

    /**
     * @dataProvider getConfigs
     */
    public function testProfilerCollectParameter($insulate)
    {
        $client = self::createClient(['test_case' => 'ProfilerCollectParameter', 'root_config' => 'config.yml']);
        if ($insulate) {
            $client->insulate();
        }

        $client->request('GET', '/profiler');
        self::assertNull($client->getProfile());

        // enable the profiler for the next request
        $client->request('GET', '/profiler?profile=1');
        self::assertIsObject($client->getProfile());

        $client->request('GET', '/profiler');
        self::assertNull($client->getProfile());
    }

    public function getConfigs()
    {
        return [
            [false],
            [true],
        ];
    }
}
