<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Event;

use Symfony\Component\Mime\Header\Headers;

class PostmarkDeliveryEvent
{
    public const CODE_INACTIVE_RECIPIENT = 406;

    private Headers $headers;

    public function __construct(
        private string $message,
        private int $errorCode,
    )
    {
        $this->headers = new Headers();
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMessageId(): ?string
    {
        if (!$this->headers->has('Message-ID')) {
            return null;
        }

        return $this->headers->get('Message-ID')->getBodyAsString();
    }

    public function setHeaders(Headers $headers): self
    {
        $this->headers = $headers;

        return $this;
    }
}
