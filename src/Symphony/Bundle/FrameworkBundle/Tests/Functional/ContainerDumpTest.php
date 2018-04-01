<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional;

/**
 * Checks that the container compiles correctly when all the bundle features are enabled.
 */
class ContainerDumpTest extends WebTestCase
{
    public function testContainerCompilationInDebug()
    {
        $client = $this->createClient(array('test_case' => 'ContainerDump', 'root_config' => 'config.yml'));

        $this->assertTrue($client->getContainer()->has('serializer'));
    }

    public function testContainerCompilation()
    {
        $client = $this->createClient(array('test_case' => 'ContainerDump', 'root_config' => 'config.yml', 'debug' => false));

        $this->assertTrue($client->getContainer()->has('serializer'));
    }
}
