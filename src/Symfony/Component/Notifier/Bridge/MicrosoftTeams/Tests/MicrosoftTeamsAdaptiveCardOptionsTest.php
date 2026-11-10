<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\AdaptiveCard;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsAdaptiveCardOptions;

final class MicrosoftTeamsAdaptiveCardOptionsTest extends TestCase
{
    public function testToArray()
    {
        $card = new AdaptiveCard();
        $options = (new MicrosoftTeamsAdaptiveCardOptions())->card($card)->card($card);

        $attachment = [
            'contentType' => 'application/vnd.microsoft.card.adaptive',
            'contentUrl' => null,
            'content' => $card->toArray(),
        ];

        $this->assertSame(['type' => 'message', 'attachments' => [$attachment, $attachment]], $options->toArray());
    }

    public function testGetRecipientIdReturnsNull()
    {
        $this->assertNull((new MicrosoftTeamsAdaptiveCardOptions())->getRecipientId());
    }
}
