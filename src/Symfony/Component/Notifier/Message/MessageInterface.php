<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Message;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.0
 */
interface MessageInterface
{
    public function getRecipientId(): ?string;

    public function getSubject(): string;

    public function getOptions(): ?MessageOptionsInterface;

    public function getTransport(): ?string;
}
