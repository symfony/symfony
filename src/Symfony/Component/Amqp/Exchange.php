<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp;

use Symfony\Component\Amqp\Exception\LogicException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Exchange extends \AMQPExchange
{
    /**
     * Special arguments:
     *
     *  * flags: if set, setFlags() will be called with its value
     *  * type: the queue type (set by setType())
     *
     * @param \AMQPChannel $channel
     * @param string       $name
     * @param array        $arguments
     */
    public function __construct(\AMQPChannel $channel, $name, array $arguments = array())
    {
        parent::__construct($channel);

        parent::setName($name);

        if (Broker::DEAD_LETTER_EXCHANGE === $name) {
            parent::setType(\AMQP_EX_TYPE_HEADERS);
            unset($arguments['type']);
        } elseif (Broker::RETRY_EXCHANGE === $name) {
            parent::setType(\AMQP_EX_TYPE_DIRECT);
            unset($arguments['type']);
        } elseif (isset($arguments['type'])) {
            parent::setType($arguments['type']);
            unset($arguments['type']);
        } else {
            parent::setType(\AMQP_EX_TYPE_DIRECT);
        }

        if (isset($arguments['flags'])) {
            parent::setFlags($arguments['flags']);
            unset($arguments['flags']);
        } else {
            parent::setFlags(\AMQP_DURABLE);
        }

        parent::declareExchange();
    }

    /**
     * Creates an Exchange based on a URI.
     *
     * The query string arguments will be used as arguments for the exchange
     * creation.
     *
     * The following arguments are "special":
     *
     * * exchange_name: The name of the exchange to create
     *
     * @param string $uri Example: amqp://guest:guest@localhost:5672/vhost?exchange_name=logs&type=fanout
     *
     * @return Exchange
     */
    public static function createFromUri($uri)
    {
        $broker = new Broker($uri);

        parse_str(parse_url($uri, PHP_URL_QUERY), $arguments);

        if (!isset($arguments['exchange_name'])) {
            throw new LogicException('The "exchange_name" must be part of the query string.');
        }
        $name = $arguments['exchange_name'];
        unset($arguments['exchange_name']);

        return $broker->createExchange($name, $arguments);
    }

    /**
     * @param string      $message
     * @param string|null $routingKey
     * @param int         $flags
     * @param array       $attributes
     *
     * @return bool
     */
    public function publish($message, $routingKey = null, $flags = \AMQP_MANDATORY, array $attributes = array())
    {
        $attributes = array_merge(array(
            'delivery_mode' => 2,
        ), $attributes);

        return parent::publish($message, $routingKey, $flags, $attributes);
    }
}
