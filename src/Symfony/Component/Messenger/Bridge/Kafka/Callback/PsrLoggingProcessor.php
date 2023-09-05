<?php

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
use Psr\Log\LogLevel;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer;

final class PsrLoggingProcessor extends AbstractCallbackProcessor
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function log(object $kafka, int $level, string $facility, string $message): void
    {
        $this->logger->log(
            match ($level) {
                0 => LogLevel::EMERGENCY,
                1 => LogLevel::ALERT,
                2 => LogLevel::CRITICAL,
                3 => LogLevel::ERROR,
                4 => LogLevel::WARNING,
                5 => LogLevel::NOTICE,
                6 => LogLevel::INFO,
                7 => LogLevel::DEBUG,
                default => LogLevel::DEBUG,
            },
            $message,
            [
                'facility' => $facility,
            ],
        );
    }

    public function consumerError(KafkaConsumer $kafka, int $err, string $reason): void
    {
        $this->logger->error($reason, [
            'error_code' => $err,
        ]);
    }

    public function producerError(Producer $kafka, int $err, string $reason): void
    {
        $this->logger->error($reason, [
            'error_code' => $err,
        ]);
    }

    public function consume(Message $message): void
    {
        match ($message->err) {
            \RD_KAFKA_RESP_ERR_NO_ERROR => $this->logger->debug(sprintf(
                'Message consumed from Kafka on partition %s: %s',
                $message->partition,
                $message->payload,
            )),
            \RD_KAFKA_RESP_ERR__PARTITION_EOF => $this->logger->info(
                'No more messages; Waiting for more'
            ),
            \RD_KAFKA_RESP_ERR__TIMED_OUT => $this->logger->debug(
                'Timed out waiting for message'
            ),
            \RD_KAFKA_RESP_ERR__TRANSPORT => $this->logger->warning(
                'Kafka Broker transport failure',
            ),
            default => $this->logger->error(sprintf(
                'Error occurred while consuming message from Kafka: %s',
                $message->errstr(),
            )),
        };
    }

    public function offsetCommit(object $kafka, int $err, $partitions): void
    {
        foreach ($partitions as $partition) {
            $this->logger->info(
                sprintf(
                    'Offset topic=%s partition=%s offset=%s code=%d successfully committed.',
                    $partition->getTopic(),
                    $partition->getPartition(),
                    $partition->getOffset(),
                    $err,
                ),
                [
                    'topic' => $partition->getTopic(),
                    'partition' => $partition->getPartition(),
                    'offset' => $partition->getOffset(),
                    'error_code' => $err,
                ],
            );
        }
    }

    public function rebalance(KafkaConsumer $kafka, int $err, $partitions): void
    {
        switch ($err) {
            case \RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                foreach ($partitions as $partition) {
                    $this->logger->info(
                        sprintf(
                            'Rebalancing %s %s %s as the assignment changed',
                            $partition->getTopic(),
                            $partition->getPartition(),
                            $partition->getOffset(),
                        ),
                        [
                            'topic' => $partition->getTopic(),
                            'partition' => $partition->getPartition(),
                            'offset' => $partition->getOffset(),
                            'error_code' => $err,
                        ],
                    );
                }
                $kafka->assign($partitions);
                break;

            case \RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                foreach ($partitions as $partition) {
                    $this->logger->info(
                        sprintf(
                            'Rebalancing %s %s %s as the assignment was revoked',
                            $partition->getTopic(),
                            $partition->getPartition(),
                            $partition->getOffset(),
                        ),
                        [
                            'topic' => $partition->getTopic(),
                            'partition' => $partition->getPartition(),
                            'offset' => $partition->getOffset(),
                            'error_code' => $err,
                        ],
                    );
                }
                $kafka->assign(null);
                break;

            default:
                foreach ($partitions as $partition) {
                    $this->logger->error(
                        sprintf(
                            'Rebalancing %s %s %s due to error code %d',
                            $partition->getTopic(),
                            $partition->getPartition(),
                            $partition->getOffset(),
                            $err,
                        ),
                        [
                            'topic' => $partition->getTopic(),
                            'partition' => $partition->getPartition(),
                            'offset' => $partition->getOffset(),
                            'error_code' => $err,
                        ],
                    );
                }
                $kafka->assign(null);
                break;
        }
    }
}
