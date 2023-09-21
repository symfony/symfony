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

use PHPUnit\Framework\AssertionFailedError;

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

    public function testLoggerAssertionWithoutTestHandler()
    {
        $client = $this->createClient(['test_case' => 'LoggerWithoutHandler', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/log');

        try {
            $this->assertLogExists('test1_Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller\LoggerController');
        } catch (AssertionFailedError $e) {
            $this->assertSame('The "monolog.handler.test" service is not available. Try registering the service "Monolog\Handler\TestHandler" as "monolog.handler.test" in your test configuration.', $e->getMessage());
        }

        try {
            $this->assertLogMatches('/(test2_).*(LoggerController)/');
        } catch (AssertionFailedError $e) {
            $this->assertSame('The "monolog.handler.test" service is not available. Try registering the service "Monolog\Handler\TestHandler" as "monolog.handler.test" in your test configuration.', $e->getMessage());
        }

        try {
            $this->assertLogContains('test3');
        } catch (AssertionFailedError $e) {
            $this->assertSame('The "monolog.handler.test" service is not available. Try registering the service "Monolog\Handler\TestHandler" as "monolog.handler.test" in your test configuration.', $e->getMessage());
        }
    }
}
