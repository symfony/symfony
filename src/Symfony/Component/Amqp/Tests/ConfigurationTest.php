<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Amqp\Configuration;
use Symfony\Component\Amqp\Exception\InvalidArgumentException;

class ConfigurationTest extends TestCase
{
    public function testValidConfiguration()
    {
        $queuesConfiguration = array(
            array(
                'name' => 'queue 1',
            ),
        );
        $exchangesConfiguration = array(
            array(
                'name' => 'exchange 1',
            ),
        );

        $configuration = new Configuration($queuesConfiguration, $exchangesConfiguration);

        $this->assertCount(1, $configuration->getQueuesConfiguration());
        $this->assertSame('queue 1', $configuration->getQueueConfiguration('queue 1')['name']);
        $this->assertNull($configuration->getQueueConfiguration('404'));

        $this->assertCount(1, $configuration->getExchangesConfiguration());
        $this->assertSame('exchange 1', $configuration->getExchangeConfiguration('exchange 1')['name']);
        $this->assertNull($configuration->getExchangeConfiguration('404'));
    }

    /**
     * @dataProvider provideInvalidConfiguration
     */
    public function testInvalidConfiguration(string $expectedMessage, array $queuesConfiguration, array $exchangesConfiguration)
    {
        try {
            new Configuration($queuesConfiguration, $exchangesConfiguration);

            $this->fail('The configuration should not be valid.');
        } catch (InvalidArgumentException $e) {
        }

        $this->assertSame($expectedMessage, $e->getMessage());
    }

    public function provideInvalidConfiguration()
    {
        yield 'missing queue name' => array(
            'The key "name" is required to configure a Queue.',
            array(
                array(
                    'arguments' => array(),
                ),
            ),
            array(),
        );

        yield '2 queues with the same name' => array(
            'A queue named "non unique name" already exists.',
            array(
                array(
                    'name' => 'non unique name',
                ),
                array(
                    'name' => 'non unique name',
                ),
            ),
            array(),
        );

        yield 'missing exchange name' => array(
            'The key "name" is required to configure an Exchange.',
            array(),
            array(
                array(
                    'arguments' => array(),
                ),
            ),
        );

        yield '2 exchanges with the same name' => array(
            'An exchange named "non unique name" already exists.',
            array(),
            array(
                array(
                    'name' => 'non unique name',
                ),
                array(
                    'name' => 'non unique name',
                ),
            ),
        );
    }
}
