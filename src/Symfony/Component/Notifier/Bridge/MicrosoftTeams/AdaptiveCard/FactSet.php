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
 * @see https://adaptivecards.io/explorer/FactSet.html
 */
final class FactSet extends Element
{
    public function __construct()
    {
        $this->options = [
            'type' => 'FactSet',
            'facts' => [],
        ];
    }

    /**
     * @return $this
     */
    public function fact(Fact $fact): static
    {
        $this->options['facts'][] = $fact->toArray();

        return $this;
    }
}
