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

final class LoggerTest extends AbstractWebTestCase
{
    public function testLoggerAssertion()
    {
        $client = $this->createClient(['test_case' => 'Logger', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/log');

        $this->assertLogExists('test1_Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller\LoggerController');
        $this->assertLogMatches('/(test2_).*(LoggerController)/');
        $this->assertLogContains('test3');
    }
}
