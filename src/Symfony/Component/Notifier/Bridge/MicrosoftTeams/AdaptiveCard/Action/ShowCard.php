<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Action;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Action;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\AdaptiveCard;

/**
 * @see https://adaptivecards.io/explorer/Action.ShowCard.html
 */
final class ShowCard extends Action
{
    public function __construct()
    {
        $this->options = [
            'type' => 'Action.ShowCard',
        ];
    }

    /**
     * @return $this
     */
    public function card(AdaptiveCard $card): static
    {
        $this->options['card'] = $card->toArray();

        return $this;
    }
}
