<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\WhatsApp;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * WhatsApp Cloud API messages are either a pre-approved template (required for
 * business-initiated messages outside the 24h customer service window) or free-form text
 * (only allowed inside that window). {@see template()} selects the former; omitting it
 * sends the {@see \Symfony\Component\Notifier\Message\ChatMessage} subject as free text.
 *
 * @author Piero Recchia <piero.recchia@gmail.com>
 */
final class WhatsAppOptions implements MessageOptionsInterface
{
    private ?string $recipientPhoneNumber = null;

    /**
     * @var array{name: string, languageCode: string, bodyParameters: list<string>}|null
     */
    private ?array $template = null;

    public function getRecipientId(): ?string
    {
        return $this->recipientPhoneNumber;
    }

    /**
     * @param string $phoneNumber recipient phone number in E.164 format, with or without a leading "+"
     */
    public function recipientPhoneNumber(string $phoneNumber): static
    {
        $this->recipientPhoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * @return array{name: string, languageCode: string, bodyParameters: list<string>}|null
     */
    public function getTemplate(): ?array
    {
        return $this->template;
    }

    /**
     * @param list<string> $bodyParameters positional values for the template's {{1}}, {{2}}, … body variables
     */
    public function template(string $name, string $languageCode, array $bodyParameters = []): static
    {
        $this->template = [
            'name' => $name,
            'languageCode' => $languageCode,
            'bodyParameters' => $bodyParameters,
        ];

        return $this;
    }

    public function toArray(): array
    {
        return [
            'recipientPhoneNumber' => $this->recipientPhoneNumber,
            'template' => $this->template,
        ];
    }
}
