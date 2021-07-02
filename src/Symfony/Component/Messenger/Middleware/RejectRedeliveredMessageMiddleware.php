<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

/**
 * @deprecated since Symfony 5.4, to be removed in 6.0. Use Symfony\Component\Messenger\Bridge\Amqp\Middleware\RejectRedeliveredMessageMiddleware instead.
 */
class RejectRedeliveredMessageMiddleware extends \Symfony\Component\Messenger\Bridge\Amqp\Middleware\RejectRedeliveredMessageMiddleware
{
}
