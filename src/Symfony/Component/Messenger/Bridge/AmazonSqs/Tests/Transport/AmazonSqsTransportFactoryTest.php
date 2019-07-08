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

class AmazonSqsTransportFactoryTest extends TestCase
{
    public function testSupportsOnlySqsTransports()
    {
        $factory = new AmazonSqsTransportFactory();

        $this->assertTrue($factory->supports('sqs://localhost', []));
        $this->assertFalse($factory->supports('redis://localhost', []));
        $this->assertFalse($factory->supports('invalid-dsn', []));
    }
}
