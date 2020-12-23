<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Exception;

use Symfony\Component\Notifier\Message\MessageInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class UnsupportedMessageTypeException extends LogicException
{
    public function __construct(string $transport, string $supported, MessageInterface $given)
    {
        $message = sprintf(
            'The "%s" transport only supports instances of "%s" (instance of "%s" given).',
            $transport,
            $supported,
            get_debug_type($given)
        );

        parent::__construct($message);
    }
}
