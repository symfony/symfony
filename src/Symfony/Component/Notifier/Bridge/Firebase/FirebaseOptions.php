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
 *
 * @experimental in 5.1
 */
abstract class FirebaseOptions implements MessageOptionsInterface
{
    /** @var string the recipient */
    private $to;

    /**
     * @var array
     *
     * @see https://firebase.google.com/docs/cloud-messaging/xmpp-server-ref.html#notification-payload-support
     */
    protected $options;

    public function __construct(string $to, array $options)
    {
        $this->to = $to;
        $this->options = $options;
    }

    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'notification' => $this->options,
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
}
