<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineBot;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @see https://developers.line.biz/en/reference/messaging-api/#send-push-message-request-body
 *
 * @author Yi-Jyun Pan <me@pan93.com>
 */
final class LineBotOptions implements MessageOptionsInterface
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
        return $this->options['to'] ?? null;
    }

    /**
     * Sets the ID of the target recipient.
     *
     * @see https://developers.line.biz/en/docs/messaging-api/getting-user-ids/
     *
     * @return $this
     */
    public function to(string $id): static
    {
        $this->options['to'] = $id;

        return $this;
    }
}
