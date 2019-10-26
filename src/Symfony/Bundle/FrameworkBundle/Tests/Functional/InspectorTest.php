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

class InspectorTest extends AbstractWebTestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testInspectorIsDisabled($insulate)
    {
        $client = $this->createClient(['test_case' => 'Inspector', 'root_config' => 'config.yml']);
        if ($insulate) {
            $client->insulate();
        }

        $client->request('GET', '/inspector');
        $this->assertNull($client->getProfile());

        // enable the profiler for the next request
        $client->enableProfiler();
        $this->assertNull($client->getProfile());
        $client->request('GET', '/inspector');
        $this->assertIsObject($client->getProfile());

        $client->request('GET', '/inspector');
        $this->assertNull($client->getProfile());
    }

    public function getConfigs()
    {
        return [
            [false],
            [true],
        ];
    }
}
