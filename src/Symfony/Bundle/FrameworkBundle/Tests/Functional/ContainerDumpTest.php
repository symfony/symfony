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

/**
 * Checks that the container compiles correctly when all the bundle features are enabled.
 */
class ContainerDumpTest extends AbstractWebTestCase
{
    public function testContainerCompilationInDebug(): void
    {
        $this->createClient(['test_case' => 'ContainerDump', 'root_config' => 'config.yml']);

        $this->assertTrue(static::getContainer()->has('serializer'));
    }

    public function testContainerCompilation(): void
    {
        $this->createClient(['test_case' => 'ContainerDump', 'root_config' => 'config.yml', 'debug' => false]);

        $this->assertTrue(static::getContainer()->has('serializer'));
    }
}
