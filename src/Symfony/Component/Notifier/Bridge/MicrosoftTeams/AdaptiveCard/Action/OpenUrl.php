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
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\SelectActionInterface;

/**
 * @see https://adaptivecards.io/explorer/Action.OpenUrl.html
 */
final class OpenUrl extends Action implements SelectActionInterface
{
    public function __construct(string $url)
    {
        $this->options = [
            'type' => 'Action.OpenUrl',
            'url' => $url,
        ];
    }
}
