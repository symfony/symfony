<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Transport;

/**
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 *
 * @see https://github.com/confluentinc/librdkafka/blob/master/CONFIGURATION.md
 */
final class KafkaOption
{
    /** @psalm-return array<string, string> */
    public static function consumer(): array
    {
        return array_merge(
            self::global(),
            [
                'group.id' => 'C',
                'group.instance.id' => 'C',
                'partition.assignment.strategy' => 'C',
                'session.timeout.ms' => 'C',
                'heartbeat.interval.ms' => 'C',
                'group.protocol.type' => 'C',
                'coordinator.query.interval.ms' => 'C',
                'max.poll.interval.ms' => 'C',
                'enable.auto.commit' => 'C',
                'auto.commit.interval.ms' => 'C',
                'enable.auto.offset.store' => 'C',
                'queued.min.messages' => 'C',
                'queued.max.messages.kbytes' => 'C',
                'fetch.wait.max.ms' => 'C',
                'fetch.message.max.bytes' => 'C',
                'max.partition.fetch.bytes' => 'C',
                'fetch.max.bytes' => 'C',
                'fetch.min.bytes' => 'C',
                'fetch.error.backoff.ms' => 'C',
                'offset.store.method' => 'C',
                'isolation.level' => 'C',
                'consume_cb' => 'C',
                'rebalance_cb' => 'C',
                'offset_commit_cb' => 'C',
                'enable.partition.eof' => 'C',
                'check.crcs' => 'C',
                'auto.commit.enable' => 'C',
                'auto.offset.reset' => 'C',
                'offset.store.path' => 'C',
                'offset.store.sync.interval.ms' => 'C',
                'consume.callback.max.messages' => 'C',
            ],
        );
    }

    /** @psalm-return array<string, string> */
    public static function producer(): array
    {
        return array_merge(
            self::global(),
            [
                'transactional.id' => 'P',
                'transaction.timeout.ms' => 'P',
                'enable.idempotence' => 'P',
                'enable.gapless.guarantee' => 'P',
                'queue.buffering.max.messages' => 'P',
                'queue.buffering.max.kbytes' => 'P',
                'queue.buffering.max.ms' => 'P',
                'linger.ms' => 'P',
                'message.send.max.retries' => 'P',
                'retries' => 'P',
                'retry.backoff.ms' => 'P',
                'queue.buffering.backpressure.threshold' => 'P',
                'compression.codec' => 'P',
                'compression.type' => 'P',
                'batch.num.messages' => 'P',
                'batch.size' => 'P',
                'delivery.report.only.error' => 'P',
                'dr_cb' => 'P',
                'dr_msg_cb' => 'P',
                'sticky.partitioning.linger.ms' => 'P',
                'request.required.acks' => 'P',
                'acks' => 'P',
                'request.timeout.ms' => 'P',
                'message.timeout.ms' => 'P',
                'delivery.timeout.ms' => 'P',
                'queuing.strategy' => 'P',
                'produce.offset.report' => 'P',
                'partitioner' => 'P',
                'partitioner_cb' => 'P',
                'msg_order_cmp' => 'P',
                'compression.level' => 'P',
            ],
        );
    }

    /** @psalm-return array<string, string> */
    public static function global(): array
    {
        return [
            'builtin.features' => '*',
            'client.id' => '*',
            'metadata.broker.list' => '*',
            'bootstrap.servers' => '*',
            'message.max.bytes' => '*',
            'message.copy.max.bytes' => '*',
            'receive.message.max.bytes' => '*',
            'max.in.flight.requests.per.connection' => '*',
            'max.in.flight' => '*',
            'topic.metadata.refresh.interval.ms' => '*',
            'metadata.max.age.ms' => '*',
            'topic.metadata.refresh.fast.interval.ms' => '*',
            'topic.metadata.refresh.fast.cnt' => '*',
            'topic.metadata.refresh.sparse' => '*',
            'topic.metadata.propagation.max.ms' => '*',
            'topic.blacklist' => '*',
            'debug' => '*',
            'socket.timeout.ms' => '*',
            'socket.blocking.max.ms' => '*',
            'socket.send.buffer.bytes' => '*',
            'socket.receive.buffer.bytes' => '*',
            'socket.keepalive.enable' => '*',
            'socket.nagle.disable' => '*',
            'socket.max.fails' => '*',
            'broker.address.ttl' => '*',
            'broker.address.family' => '*',
            'socket.connection.setup.timeout.ms' => '*',
            'connections.max.idle.ms' => '*',
            'reconnect.backoff.jitter.ms' => '*',
            'reconnect.backoff.ms' => '*',
            'reconnect.backoff.max.ms' => '*',
            'statistics.interval.ms' => '*',
            'enabled_events' => '*',
            'error_cb' => '*',
            'throttle_cb' => '*',
            'stats_cb' => '*',
            'log_cb' => '*',
            'log_level' => '*',
            'log.queue' => '*',
            'log.thread.name' => '*',
            'enable.random.seed' => '*',
            'log.connection.close' => '*',
            'background_event_cb' => '*',
            'socket_cb' => '*',
            'connect_cb' => '*',
            'closesocket_cb' => '*',
            'open_cb' => '*',
            'resolve_cb' => '*',
            'opaque' => '*',
            'default_topic_conf' => '*',
            'internal.termination.signal' => '*',
            'api.version.request' => '*',
            'api.version.request.timeout.ms' => '*',
            'api.version.fallback.ms' => '*',
            'broker.version.fallback' => '*',
            'allow.auto.create.topics' => '*',
            'security.protocol' => '*',
            'ssl.cipher.suites' => '*',
            'ssl.curves.list' => '*',
            'ssl.sigalgs.list' => '*',
            'ssl.key.location' => '*',
            'ssl.key.password' => '*',
            'ssl.key.pem' => '*',
            'ssl_key' => '*',
            'ssl.certificate.location' => '*',
            'ssl.certificate.pem' => '*',
            'ssl_certificate' => '*',
            'ssl.ca.location' => '*',
            'ssl.ca.pem' => '*',
            'ssl_ca' => '*',
            'ssl.ca.certificate.stores' => '*',
            'ssl.crl.location' => '*',
            'ssl.keystore.location' => '*',
            'ssl.keystore.password' => '*',
            'ssl.providers' => '*',
            'ssl.engine.location' => '*',
            'ssl.engine.id' => '*',
            'ssl_engine_callback_data' => '*',
            'enable.ssl.certificate.verification' => '*',
            'ssl.endpoint.identification.algorithm' => '*',
            'ssl.certificate.verify_cb' => '*',
            'sasl.mechanisms' => '*',
            'sasl.mechanism' => '*',
            'sasl.kerberos.service.name' => '*',
            'sasl.kerberos.principal' => '*',
            'sasl.kerberos.kinit.cmd' => '*',
            'sasl.kerberos.keytab' => '*',
            'sasl.kerberos.min.time.before.relogin' => '*',
            'sasl.username' => '*',
            'sasl.password' => '*',
            'sasl.oauthbearer.config' => '*',
            'enable.sasl.oauthbearer.unsecure.jwt' => '*',
            'oauthbearer_token_refresh_cb' => '*',
            'sasl.oauthbearer.method' => '*',
            'sasl.oauthbearer.client.id' => '*',
            'sasl.oauthbearer.client.secret' => '*',
            'sasl.oauthbearer.scope' => '*',
            'sasl.oauthbearer.extensions' => '*',
            'sasl.oauthbearer.token.endpoint.url' => '*',
            'plugin.library.paths' => '*',
            'interceptors' => '*',
            'client.rack' => '*',
        ];
    }
}
