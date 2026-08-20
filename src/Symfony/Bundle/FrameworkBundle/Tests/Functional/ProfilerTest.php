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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\EventListener\ProfilerListener;

class ProfilerTest extends AbstractWebTestCase
{
    #[DataProvider('getConfigs')]
    public function testProfilerIsDisabled($insulate)
    {
        $client = $this->createClient(['test_case' => 'Profiler', 'root_config' => 'config.yml']);
        if ($insulate) {
            $client->insulate();
        }

        $client->request('GET', '/profiler');
        $this->assertNull($client->getProfile());

        // enable the profiler for the next request
        $client->enableProfiler();
        $this->assertNull($client->getProfile());
        $client->request('GET', '/profiler');
        $this->assertIsObject($client->getProfile());

        $client->request('GET', '/profiler');
        $this->assertNull($client->getProfile());
    }

    #[DataProvider('getConfigs')]
    public function testProfilerCollectParameter($insulate)
    {
        $client = $this->createClient(['test_case' => 'ProfilerCollectParameter', 'root_config' => 'config.yml']);
        if ($insulate) {
            $client->insulate();
        }

        $client->request('GET', '/profiler');
        $this->assertNull($client->getProfile());

        // enable the profiler for the next request
        $client->request('GET', '/profiler?profile=1');
        $this->assertIsObject($client->getProfile());

        $client->request('GET', '/profiler');
        $this->assertNull($client->getProfile());
    }

    #[DataProvider('getConfigs')]
    public function testProfilerExclusions($insulate)
    {
        if (8 > (new \ReflectionMethod(ProfilerListener::class, '__construct'))->getNumberOfParameters()) {
            $this->markTestSkipped('This test requires symfony/http-kernel 8.2 or higher.');
        }

        $client = $this->createClient(['test_case' => 'ProfilerExclusions', 'root_config' => 'config.yml']);
        if ($insulate) {
            $client->insulate();
        }

        // a request whose path matches "excluded_paths" is not profiled and gets no debug token
        $client->request('GET', '/session');
        $this->assertNull($client->getProfile());
        $this->assertFalse($client->getResponse()->headers->has('X-Debug-Token'));

        // a response whose status code matches "excluded_http_codes" is not profiled either
        $client->request('GET', '/not-found');
        $this->assertSame(404, $client->getResponse()->getStatusCode());
        $this->assertNull($client->getProfile());

        // any other request is still profiled
        $client->request('GET', '/profiler');
        $this->assertIsObject($client->getProfile());
    }

    public static function getConfigs()
    {
        return [
            [false],
            [true],
        ];
    }
}
