<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Adapter\Factory;

use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\SenderInterface;

/**
 * Creates a Messenger adapter.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
interface AdapterFactoryInterface
{
    public function createReceiver(string $dsn, array $options): ReceiverInterface;

    public function createSender(string $dsn, array $options): SenderInterface;

    public function supports(string $dsn, array $options): bool;
}
