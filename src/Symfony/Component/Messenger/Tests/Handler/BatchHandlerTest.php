<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;

class BatchHandlerTest extends TestCase
{
    public function testSynchronousAckReturnsTheResult()
    {
        $handler = new BatchHandlerTestHandler();

        $this->assertSame('the result', $handler(new \stdClass()));
    }

    public function testSynchronousNackRethrowsTheError()
    {
        $handler = new BatchHandlerTestHandler(new \RuntimeException('handler failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handler failed');

        $handler(new \stdClass());
    }
}

class BatchHandlerTestHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __construct(
        private ?\Throwable $error = null,
    ) {
    }

    public function __invoke(object $message, ?Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    private function process(array $jobs): void
    {
        foreach ($jobs as [, $ack]) {
            if ($this->error) {
                $ack->nack($this->error);
            } else {
                $ack->ack('the result');
            }
        }
    }
}
