<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsSluzba;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

final class SmsSluzbaOptions implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function sendAt(\DateTime $sendAt): static
    {
        $sendAt->setTimezone(new \DateTimeZone('Europe/Prague'));

        $this->options['send_at'] = $sendAt->format('YmdHis');

        return $this;
    }
}
