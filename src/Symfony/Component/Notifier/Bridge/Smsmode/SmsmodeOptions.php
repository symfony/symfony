<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsmode;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class SmsmodeOptions implements MessageOptionsInterface
{
    public function __construct(
        private array $options = [],
    ) {
    }

    public function getRecipientId(): ?string
    {
        return null;
    }

    /**
     * @return $this
     */
    public function refClient(string $refClient): static
    {
        $this->options['refClient'] = $refClient;

        return $this;
    }

    /**
     * @return $this
     */
    public function sentDate(string $sentDate): static
    {
        $this->options['sentDate'] = $sentDate;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
