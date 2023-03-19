<?php

namespace Symfony\Component\Messenger\WorkerExecution;

use RuntimeException;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * Example worker command:
 *
 *   bin/console messenger:consume queue_a queue_b queue_b_low queue_b_lowest queue_c \
 *     --strategy="com.symfony.ranked" \
 *     --strategy-config="{\"ranks\": [1, 1, 2, 3, 1]}"
 *
 * The command above will result in the following execution pattern:
 *
 *  1. (queue_a, queue_b, queue_c)
 *  2. queue_b_low
 *  3. queue_b_lowest
 */
final class RankedWorkerExecutionStrategy implements WorkerExecutionStrategyInterface
{
    /**
     * * ranks: int[]. Queue receivers with the same rank integer value will form a single group. Groups are executed
     *      from lowest to highest integer value.
     */
    private array $options;

    public function __construct(array $options)
    {
        $this->options = $options;

        $this->validateOptions($options);
    }

    private function validateOptions(array $options): void
    {
        if (!array_key_exists('ranks', $options) || !is_array($options['ranks'])) {
            throw new RuntimeException('Invalid worker ranked strategy options. Missing key "ranks" or it is not an array');
        }

        foreach ($options['ranks'] as $rankNumber) {
            if (!is_int($rankNumber)) {
                throw new RuntimeException('Invalid worker ranked strategy options. One of the ranks is not an integer');
            }
        }
    }

    public static function getAlias(): string
    {
        return 'com.symfony.ranked';
    }

    public function processQueueTasks(WorkerExecutionStrategyContext $context): WorkerExecutionStrategyResult
    {
        $envelopeHandled = false;

        foreach ($this->groupReceiversByRanks($context->getReceivers()) as $receiversGroup) {
            foreach ($receiversGroup as $transportName => $receiver) {
                if ($context->getQueueNames()) {
                    $envelopes = $receiver->getFromQueues($context->getQueueNames());
                } else {
                    $envelopes = $receiver->get();
                }

                foreach ($envelopes as $envelope) {
                    $envelopeHandled = true;

                    $result = $context->handleMessage($envelope, $transportName);

                    if ($result->shouldStop) {
                        break 3;
                    }
                }
            }

            // after handling a single grouped rank of receivers, quit and start the loop again
            // this should prevent multiple lower priority receivers from
            // blocking too long before the higher priority are checked
            if ($envelopeHandled) {
                break;
            }
        }

        return new WorkerExecutionStrategyResult($envelopeHandled);
    }

    /**
     * @param ReceiverInterface[] $receivers Where the key is the transport name
     * @return array<int, array<string, ReceiverInterface>> Ordered groups of receivers by ranks number
     */
    private function groupReceiversByRanks(array $receivers): array
    {
        $receiversRanks = $this->options['ranks'];
        $receiversValues = array_values($receivers);
        $receiversKeys = array_keys($receivers);

        if (count($receiversRanks) !== count($receivers)) {
            throw new RuntimeException('Worker ranked strategy: The count of queue receivers does not match the count of their ranks');
        }

        /**
         * @var array<int, array<string, ReceiverInterface>> $receiversGroupedByRanks
         */
        $receiversGroupedByRanks = [];
        foreach ($receiversValues as $index => $receiver) {
            $receiversGroupedByRanks[(int) $receiversRanks[$index]][$receiversKeys[$index]] = $receiver;
        }

        uksort($receiversGroupedByRanks, static function ($rankA, $rankB) {
            return $rankA <=> $rankB;
        });

        return $receiversGroupedByRanks;
    }
}
