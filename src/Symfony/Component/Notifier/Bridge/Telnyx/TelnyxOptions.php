<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telnyx;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @author Mihail Krasilnikov <mihail.krasilnikov.j@gmail.com>
 *
 * @see https://developers.telnyx.com/docs/api/v2/messaging/Messages#createMessage
 */
final class TelnyxOptions implements MessageOptionsInterface
{
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['to'] ?? null;
    }

    public function to(string $number): self
    {
        $this->options['to'] = $number;

        return $this;
    }

    public function from(string $from): self
    {
        $this->options['from'] = $from;

        return $this;
    }

    public function mediaUrls(array $mediaUrls): self
    {
        $this->options['media_urls'] = $mediaUrls;

        return $this;
    }

    public function messagingProfileId(string $messagingProfileId): self
    {
        $this->options['messaging_profile_id'] = $messagingProfileId;

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->options['subject'] = $subject;

        return $this;
    }

    public function text(string $text): self
    {
        $this->options['text'] = $text;

        return $this;
    }

    public function type(string $type): self
    {
        $this->options['type'] = $type;

        return $this;
    }

    public function useProfileWebhooks(bool $useProfileWebhooks): self
    {
        $this->options['use_profile_webhooks'] = $useProfileWebhooks;

        return $this;
    }

    public function webhookFailoverUrl(string $webhookFailoverUrl): self
    {
        $this->options['webhook_failover_url'] = $webhookFailoverUrl;

        return $this;
    }

    public function webhookUrll(string $webhookUrl): self
    {
        $this->options['webhook_url'] = $webhookUrl;

        return $this;
    }
}
