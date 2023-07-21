<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Callback;

use Psr\Log\LoggerInterface;
use RdKafka\KafkaConsumer;
use RdKafka\TopicPartition;

final class LoggingRebalanceCallback
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<TopicPartition>|null $topicPartitions
     */
    public function __invoke(KafkaConsumer $kafka, ?int $err, array $topicPartitions = null): void
    {
        $topicPartitions = $topicPartitions ?? [];

        switch ($err) {
            case \RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                foreach ($topicPartitions as $topicPartition) {
                    $this->logger->info(
                        sprintf(
                            'Rebalancing %s %s %s as the assignment changed',
                            $topicPartition->getTopic(),
                            $topicPartition->getPartition(),
                            $topicPartition->getOffset(),
                        ),
                        [
                            'topic' => $topicPartition->getTopic(),
                            'partition' => $topicPartition->getPartition(),
                            'offset' => $topicPartition->getOffset(),
                            'error_code' => $err,
                        ],
                    );
                }
                $kafka->assign($topicPartitions);
                break;

            case \RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                foreach ($topicPartitions as $topicPartition) {
                    $this->logger->info(
                        sprintf(
                            'Rebalancing %s %s %s as the assignment was revoked',
                            $topicPartition->getTopic(),
                            $topicPartition->getPartition(),
                            $topicPartition->getOffset(),
                        ),
                        [
                            'topic' => $topicPartition->getTopic(),
                            'partition' => $topicPartition->getPartition(),
                            'offset' => $topicPartition->getOffset(),
                            'error_code' => $err,
                        ],
                    );
                }
                $kafka->assign(null);
                break;

            default:
                if (\count($topicPartitions)) {
                    foreach ($topicPartitions as $topicPartition) {
                        $this->logger->error(
                            sprintf(
                                'Rebalancing %s %s %s due to error code %d',
                                $topicPartition->getTopic(),
                                $topicPartition->getPartition(),
                                $topicPartition->getOffset(),
                                $err,
                            ),
                            [
                                'topic' => $topicPartition->getTopic(),
                                'partition' => $topicPartition->getPartition(),
                                'offset' => $topicPartition->getOffset(),
                                'error_code' => $err,
                            ],
                        );
                    }
                } else {
                    $this->logger->error(
                        sprintf(
                            'Rebalancing error code %d',
                            $err,
                        ),
                        [
                            'error_code' => $err,
                        ]
                    );
                }
                $kafka->assign(null);
                break;
        }
    }
}
