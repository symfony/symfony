<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard;

/**
 * @see https://adaptivecards.io/explorer/ActionSet.html
 */
final class ActionSet extends Element
{
    public function __construct()
    {
        $this->options = [
            'type' => 'ActionSet',
            'actions' => [],
        ];
    }

    /**
     * @return $this
     */
    public function action(ActionInterface $action): static
    {
        $this->options['actions'][] = $action->toArray();

        return $this;
    }
}
