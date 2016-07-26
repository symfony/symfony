<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Casts Amqp related classes to array representation.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class AmqpCaster
{
    private static $flags = array(
        AMQP_DURABLE => 'AMQP_DURABLE',
        AMQP_PASSIVE => 'AMQP_PASSIVE',
        AMQP_EXCLUSIVE => 'AMQP_EXCLUSIVE',
        AMQP_AUTODELETE => 'AMQP_AUTODELETE',
        AMQP_INTERNAL => 'AMQP_INTERNAL',
        AMQP_NOLOCAL => 'AMQP_NOLOCAL',
        AMQP_AUTOACK => 'AMQP_AUTOACK',
        AMQP_IFEMPTY => 'AMQP_IFEMPTY',
        AMQP_IFUNUSED => 'AMQP_IFUNUSED',
        AMQP_MANDATORY => 'AMQP_MANDATORY',
        AMQP_IMMEDIATE => 'AMQP_IMMEDIATE',
        AMQP_MULTIPLE => 'AMQP_MULTIPLE',
        AMQP_NOWAIT => 'AMQP_NOWAIT',
        AMQP_REQUEUE => 'AMQP_REQUEUE',
    );

    private static $exchangeTypes = array(
        AMQP_EX_TYPE_DIRECT => 'AMQP_EX_TYPE_DIRECT',
        AMQP_EX_TYPE_FANOUT => 'AMQP_EX_TYPE_FANOUT',
        AMQP_EX_TYPE_TOPIC => 'AMQP_EX_TYPE_TOPIC',
        AMQP_EX_TYPE_HEADERS => 'AMQP_EX_TYPE_HEADERS',
    );

    public static function castConnection(\AMQPConnection $c, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        // BC layer in the amqp lib
        if (method_exists($c, 'getReadTimeout')) {
            $timeout = $c->getReadTimeout();
        } else {
            $timeout = $c->getTimeout();
        }

        $a += array(
            $prefix.'isConnected' => $c->isConnected(),
            $prefix.'login' => $c->getLogin(),
            $prefix.'password' => $c->getPassword(),
            $prefix.'host' => $c->getHost(),
            $prefix.'port' => $c->getPort(),
            $prefix.'vhost' => $c->getVhost(),
            $prefix.'readTimeout' => $timeout,
        );

        return $a;
    }

    public static function castChannel(\AMQPChannel $c, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        $a += array(
            $prefix.'isConnected' => $c->isConnected(),
            $prefix.'channelId' => $c->getChannelId(),
            $prefix.'prefetchSize' => $c->getPrefetchSize(),
            $prefix.'prefetchCount' => $c->getPrefetchCount(),
            $prefix.'connection' => $c->getConnection(),
        );

        return $a;
    }

    public static function castQueue(\AMQPQueue $c, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        $a += array(
            $prefix.'name' => $c->getName(),
            $prefix.'flags' => self::extractFlags($c->getFlags()),
            $prefix.'arguments' => $c->getArguments(),
            $prefix.'connection' => $c->getConnection(),
            $prefix.'channel' => $c->getChannel(),
        );

        return $a;
    }

    public static function castExchange(\AMQPExchange $c, array $a, Stub $stub, $isNested)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        $a += array(
            $prefix.'name' => $c->getName(),
            $prefix.'flags' => self::extractFlags($c->getFlags()),
            $prefix.'type' => isset(self::$exchangeTypes[$c->getType()]) ? new ConstStub(self::$exchangeTypes[$c->getType()], $c->getType()) : $c->getType(),
            $prefix.'arguments' => $c->getArguments(),
            $prefix.'channel' => $c->getChannel(),
            $prefix.'connection' => $c->getConnection(),
        );

        return $a;
    }

    public static function castEnvelope(\AMQPEnvelope $c, array $a, Stub $stub, $isNested, $filter = 0)
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        if (!($filter & Caster::EXCLUDE_VERBOSE)) {
            $a += array($prefix.'body' => $c->getBody());
        }

        $a += array(
            $prefix.'routingKey' => $c->getRoutingKey(),
            $prefix.'deliveryTag' => $c->getDeliveryTag(),
            $prefix.'deliveryMode' => new ConstStub($c->getDeliveryMode().(2 === $c->getDeliveryMode() ? ' (persistent)' : ' (non-persistent)'), $c->getDeliveryMode()),
            $prefix.'exchangeName' => $c->getExchangeName(),
            $prefix.'isRedelivery' => $c->isRedelivery(),
            $prefix.'contentType' => $c->getContentType(),
            $prefix.'contentEncoding' => $c->getContentEncoding(),
            $prefix.'type' => $c->getType(),
            $prefix.'timestamp' => $c->getTimeStamp(),
            $prefix.'priority' => $c->getPriority(),
            $prefix.'expiration' => $c->getExpiration(),
            $prefix.'userId' => $c->getUserId(),
            $prefix.'appId' => $c->getAppId(),
            $prefix.'messageId' => $c->getMessageId(),
            $prefix.'replyTo' => $c->getReplyTo(),
            $prefix.'correlationId' => $c->getCorrelationId(),
            $prefix.'headers' => $c->getHeaders(),
        );

        return $a;
    }

    private static function extractFlags($flags)
    {
        $flagsArray = array();

        foreach (self::$flags as $value => $name) {
            if ($flags & $value) {
                $flagsArray[] = $name;
            }
        }

        if (!$flagsArray) {
            $flagsArray = array('AMQP_NOPARAM');
        }

        return new ConstStub(implode('|', $flagsArray), $flags);
    }
}
