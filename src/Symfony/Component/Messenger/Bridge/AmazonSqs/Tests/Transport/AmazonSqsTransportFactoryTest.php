<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class AmazonSqsTransportFactoryTest extends TestCase
{
    public function testSupportsOnlySqsTransports()
    {
        $factory = new AmazonSqsTransportFactory();

        $this->assertTrue($factory->supports('sqs://localhost', []));
        $this->assertTrue($factory->supports('https://sqs.us-east-2.amazonaws.com/123456789012/ab1-MyQueue-A2BCDEF3GHI4', []));
        $this->assertTrue($factory->supports('https://sqs.cn-north-1.amazonaws.com.cn/123456789012/ab1-MyQueue-A2BCDEF3GHI4', []));
        $this->assertTrue($factory->supports('https://sqs.eusc-de-east-1.amazonaws.eu/123456789012/ab1-MyQueue-A2BCDEF3GHI4', []));
        $this->assertFalse($factory->supports('https://sqs.us-east-2.notamazon.com/123456789012/queue', []));
        $this->assertFalse($factory->supports('https://sqs.us-east-2.amazonaws.evil.com/123456789012/queue', []));
        $this->assertFalse($factory->supports('https://sqs.us-east-2.amazonaws.co.uk/123456789012/queue', []));
        $this->assertFalse($factory->supports('redis://localhost', []));
        $this->assertFalse($factory->supports('invalid-dsn', []));
    }

    public function testOptionsAreNotExposedInTrace()
    {
        if (!class_exists(\SensitiveParameterValue::class)) {
            $this->markTestSkipped('Sensitive parameter support requires PHP 8.2.');
        }

        $factory = new AmazonSqsTransportFactory();

        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            $factory->createTransport('sqs://default/queue', ['secret_key' => 's3cr3t', 'foo' => 'bar'], $this->createStub(SerializerInterface::class));
            $this->fail('An exception should have been thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringNotContainsString('s3cr3t', json_encode($e->getTrace(), \JSON_THROW_ON_ERROR));
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }
}
