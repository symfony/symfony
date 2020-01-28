<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpSender as BridgeAmqpSender;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 5.1, use "%s" instead. The AmqpExt transport has been moved to package "symfony/amqp-messenger" and will not be included by default in 6.0. Run "composer require symfony/amqp-messenger".', AmqpSender::class, BridgeAmqpSender::class), E_USER_DEPRECATED);

class_exists(BridgeAmqpSender::class);

if (false) {
    /**
     * @deprecated since Symfony 5.1, to be removed in 6.0. Use symfony/amqp-messenger instead.
     */
    class AmqpSender
    {
    }
}

