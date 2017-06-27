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
     * Create a new Exchange.
     *
     * Special arguments:
     *  * flags: if set, setFlags() will be called with its value
     *  * type: the queue type (set by setType())
     */
    public function __construct(\AMQPChannel $channel, string $name, array $arguments = array())
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

        parent::setFlags($arguments['flags'] ?? \AMQP_DURABLE);
        unset($arguments['flags']);

        parent::declareExchange();
    }

    /**
     * Creates an Exchange based on a URI.
     *
     * The query string arguments will be used as arguments for the exchange
     * creation.
     *
     * The following arguments are "special":
     * * exchange_name: The name of the exchange to create
     */
    public static function createFromUri(string $uri): self
    {
        $broker = Broker::createWithDsn($uri);

        parse_str(parse_url($uri, PHP_URL_QUERY), $arguments);

        if (!isset($arguments['exchange_name'])) {
            throw new LogicException('The "exchange_name" must be part of the query string.');
        }

        $name = $arguments['exchange_name'];
        unset($arguments['exchange_name']);

        return $broker->createExchange($name, $arguments);
    }

    public function publish($message, $routingKey = null, $flags = null, array $attributes = null)
    {
        $attributes = array_merge(array(
            'delivery_mode' => 2,
        ), $attributes);

        return parent::publish($message, $routingKey, $flags ?? \AMQP_MANDATORY, $attributes);
    }
}
