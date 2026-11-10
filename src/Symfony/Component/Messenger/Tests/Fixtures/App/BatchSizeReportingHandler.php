<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures\App;

use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

final class BatchSizeReportingHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __invoke(DummyMessage $message, ?Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    private function getBatchSize(): int
    {
        return 2;
    }

    private function process(array $jobs): void
    {
        BatchHandlingMiddleware::$log[] = 'process '.implode(',', array_map(static fn (array $job) => $job[0]->getMessage(), $jobs));

        foreach ($jobs as [$job]) {
            if ('fail' === $job->getMessage()) {
                throw new \RuntimeException('Parallel handler failed.');
            }
        }

        $batchSize = \count($jobs);

        foreach ($jobs as [, $ack]) {
            $ack->ack($batchSize);
        }
    }
}
