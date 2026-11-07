<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\AdaptiveCard;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * Sends Adaptive Cards to a Workflows webhook.
 *
 * @author Edie Lemoine <edie@edielemoine.nl>
 *
 * @see https://learn.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/connectors-using#send-adaptive-cards-using-an-incoming-webhook
 */
final class MicrosoftTeamsAdaptiveCardOptions implements MessageOptionsInterface
{
    private array $attachments = [];

    /**
     * @return $this
     */
    public function card(AdaptiveCard $card): static
    {
        $this->attachments[] = [
            'contentType' => 'application/vnd.microsoft.card.adaptive',
            'contentUrl' => null,
            'content' => $card->toArray(),
        ];

        return $this;
    }

    public function toArray(): array
    {
        return [
            'type' => 'message',
            'attachments' => $this->attachments,
        ];
    }

    public function getRecipientId(): ?string
    {
        return null;
    }
}
