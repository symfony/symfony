<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OneSignal;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
final class OneSignalOptions implements MessageOptionsInterface
{
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * @return $this
     */
    public static function fromNotification(Notification $notification): static
    {
        $options = new self();
        $options->headings(['en' => $notification->getSubject()]);
        $options->contents(['en' => $notification->getContent()]);

        return $options;
    }

    /**
     * @return $this
     */
    public function headings(array $headings): static
    {
        $this->options['headings'] = $headings;

        return $this;
    }

    /**
     * @return $this
     */
    public function contents(array $contents): static
    {
        $this->options['contents'] = $contents;

        return $this;
    }

    /**
     * @return $this
     */
    public function url(string $url): static
    {
        $this->options['url'] = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function data(array $data): static
    {
        $this->options['data'] = $data;

        return $this;
    }

    /**
     * @return $this
     */
    public function sendAfter(\DateTimeInterface $datetime): static
    {
        $this->options['send_after'] = $datetime->format('Y-m-d H:i:sO');

        return $this;
    }

    /**
     * @return $this
     */
    public function externalId(string $externalId): static
    {
        $this->options['external_id'] = $externalId;

        return $this;
    }

    /**
     * @return $this
     */
    public function recipient(string $id): static
    {
        $this->options['recipient_id'] = $id;

        return $this;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'] ?? null;
    }

    public function toArray(): array
    {
        $options = $this->options;
        unset($options['recipient_id']);

        return $options;
    }
}
