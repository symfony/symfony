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
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;

/**
 * Manipulates the headers and the body of a Message.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MessageListener implements EventSubscriberInterface
{
    private $headers;
    private $renderer;

    public function __construct(Headers $headers = null, BodyRendererInterface $renderer = null)
    {
        $this->headers = $headers;
        $this->renderer = $renderer;
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

        $headers = $message->getHeaders();
        foreach ($this->headers->all() as $name => $header) {
            if (!$headers->has($name)) {
                $headers->add($header);
            } else {
                if (Headers::isUniqueHeader($name)) {
                    continue;
                }
                $headers->add($header);
            }
        }
        $message->setHeaders($headers);
    }

    private function renderMessage(Message $message): void
    {
        if (!$this->renderer) {
            return;
        }

        $this->renderer->render($message);
    }

    public static function getSubscribedEvents()
    {
        return [
            MessageEvent::class => 'onMessage',
        ];
    }
}
