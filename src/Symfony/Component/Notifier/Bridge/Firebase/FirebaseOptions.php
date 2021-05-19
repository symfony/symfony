<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * @see https://firebase.google.com/docs/cloud-messaging/xmpp-server-ref.html
 */
abstract class FirebaseOptions implements MessageOptionsInterface
{
    private $to;

    /**
     * @see https://firebase.google.com/docs/cloud-messaging/xmpp-server-ref.html#notification-payload-support
     */
    protected $options;

    private $data;

    public function __construct(string $to, array $options, array $data = [])
    {
        $this->to = $to;
        $this->options = $options;
        $this->data = $data;
    }

    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'notification' => $this->options,
            'data' => $this->data,
        ];
    }

    public function getRecipientId(): ?string
    {
        return $this->to;
    }

    public function title(string $title): self
    {
        $this->options['title'] = $title;

        return $this;
    }

    public function body(string $body): self
    {
        $this->options['body'] = $body;

        return $this;
    }

    public function data(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
