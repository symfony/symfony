<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Transport;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\MessageInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
interface TransportInterface
{
    /**
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RuntimeException
     * @throws TransportExceptionInterface
     */
    public function send(MessageInterface $message): void;

    public function supports(MessageInterface $message): bool;

    public function __toString(): string;
}
