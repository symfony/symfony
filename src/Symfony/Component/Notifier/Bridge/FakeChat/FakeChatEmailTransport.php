<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeChat;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FakeChatEmailTransport extends AbstractTransport
{
    protected const HOST = 'default';

    private MailerInterface $mailer;
    private string $to;
    private string $from;

    public function __construct(MailerInterface $mailer, string $to, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->mailer = $mailer;
        $this->to = $to;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('fakechat+email://%s?to=%s&from=%s', $this->getEndpoint(), $this->to, $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    /**
     * @param MessageInterface|ChatMessage $message
     *
     * @throws TransportExceptionInterface
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$this->supports($message)) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $subject = 'New Chat message without specified recipient!';
        if (null !== $message->getRecipientId()) {
            $subject = sprintf('New Chat message for recipient: %s', $message->getRecipientId());
        }

        $email = (new Email())
            ->from($this->from)
            ->to($this->to)
            ->subject($subject)
            ->html($message->getSubject())
            ->text($message->getSubject());

        if ('default' !== $transportName = $this->getEndpoint()) {
            $email->getHeaders()->addTextHeader('X-Transport', $transportName);
        }

        $this->mailer->send($email);

        return new SentMessage($message, (string) $this);
    }
}
