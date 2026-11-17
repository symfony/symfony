<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Message;

/**
 * Manipulates the headers and the body of a Message.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MessageListener implements EventSubscriberInterface
{
    public const HEADER_SET_IF_EMPTY = 1;
    public const HEADER_ADD = 2;
    public const HEADER_REPLACE = 3;
    public const HEADER_EXPRESSION = 4;
    public const DEFAULT_RULES = [
        'from' => self::HEADER_SET_IF_EMPTY,
        'return-path' => self::HEADER_SET_IF_EMPTY,
        'reply-to' => self::HEADER_ADD,
        'to' => self::HEADER_SET_IF_EMPTY,
        'cc' => self::HEADER_ADD,
        'bcc' => self::HEADER_ADD,
        'subject' => self::HEADER_EXPRESSION,
    ];

    private array $headerRules = [];

    public function __construct(
        private ?Headers $headers = null,
        private ?BodyRendererInterface $renderer = null,
        array $headerRules = self::DEFAULT_RULES,
    ) {
        foreach ($headerRules as $headerName => $rule) {
            $this->addHeaderRule($headerName, $rule);
        }
    }

    public function addHeaderRule(string $headerName, int $rule): void
    {
        if ($rule < 1 || $rule > 4) {
            throw new InvalidArgumentException(\sprintf('The "%d" rule is not supported.', $rule));
        }

        $this->headerRules[strtolower($headerName)] = $rule;
    }

    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();
        if (!$message instanceof Message) {
            return;
        }

        $this->setHeaders($message);
        $this->renderMessage($message);
    }

    private function setHeaders(Message $message): void
    {
        if (!$this->headers) {
            return;
        }

        $messageHeaders = $message->getHeaders();
        foreach ($this->headers->all() as $name => $defaultHeader) {
            $headerRule = $this->headerRules[$name] ?? self::HEADER_SET_IF_EMPTY;

            if (!$messageHeaders->has($name) && $headerRule !== self::HEADER_EXPRESSION) {
                $messageHeaders->add($defaultHeader);

                continue;
            }

            switch ($headerRule) {
                case self::HEADER_SET_IF_EMPTY:
                    break;

                case self::HEADER_REPLACE:
                    $messageHeaders->remove($name);
                    $messageHeaders->add($defaultHeader);

                    break;

                case self::HEADER_ADD:
                    if (!Headers::isUniqueHeader($name)) {
                        $messageHeaders->add($defaultHeader);

                        break;
                    }

                    $h = $messageHeaders->get($name);
                    if (!$h instanceof MailboxListHeader || !$defaultHeader instanceof MailboxListHeader) {
                        throw new RuntimeException(\sprintf('Unable to set header "%s".', $name));
                    }

                    foreach ($defaultHeader->getAddressList() as $address) {
                        $h->addAddress($address);
                    }

                    break;

                case self::HEADER_EXPRESSION:
                    $expressionLanguage = new ExpressionLanguage();
                    $body = $expressionLanguage->evaluate($defaultHeader->getBody(), ['headers' => $messageHeaders]);

                    $defaultHeader->setBody($body);

                    $messageHeaders->remove($name);
                    $messageHeaders->add($defaultHeader);

                    break;
            }
        }
    }

    private function renderMessage(Message $message): void
    {
        if (!$this->renderer) {
            return;
        }

        $this->renderer->render($message);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => 'onMessage',
        ];
    }
}
