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
 * @author Jeroen Spee <https://github.com/Jeroeny>
 *
 * @see https://firebase.google.com/docs/cloud-messaging/xmpp-server-ref.html
 */
abstract class FirebaseOptions implements MessageOptionsInterface
{
    private string $to;

    /**
     * @see https://firebase.google.com/docs/cloud-messaging/xmpp-server-ref.html#notification-payload-support
     */
    protected $options;

    private array $data;

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

    /**
     * @return $this
     */
    public function title(string $title): static
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * @return $this
     */
    public function body(string $body): static
    {
        $this->options['body'] = $body;

        return $this;
    }

    /**
     * @return $this
     */
    public function data(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
