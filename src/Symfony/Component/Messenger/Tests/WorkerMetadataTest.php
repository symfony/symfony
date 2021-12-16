<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\WorkerMetadata;

/**
 * @author Oleg Krasavin <okwinza@gmail.com>
 */
class WorkerMetadataTest extends TestCase
{
    public function testItReturnsDefaultValuesIfNoneProvided()
    {
        $metadata = new WorkerMetadata([]);

        $this->assertNull($metadata->getQueueNames());
        $this->assertSame([], $metadata->getTransportNames());
    }

    public function testItReturnsProvidedMetadata()
    {
        $data = [
            'queueNames' => ['c', 'b', 'a'],
            'transportNames' => ['a', 'b', 'c'],
        ];

        $metadata = new WorkerMetadata($data);

        $this->assertSame($data['queueNames'], $metadata->getQueueNames());
        $this->assertSame($data['transportNames'], $metadata->getTransportNames());
    }

    public function testItSetsMetadataViaSetter()
    {
        $data = [
            'queueNames' => ['c', 'b', 'a'],
            'transportNames' => ['a', 'b', 'c'],
        ];

        $metadata = new WorkerMetadata([]);

        $metadata->set($data);

        $this->assertSame($data['queueNames'], $metadata->getQueueNames());
        $this->assertSame($data['transportNames'], $metadata->getTransportNames());
    }
}
