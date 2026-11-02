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
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\AssociatedInputs;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\SelectActionInterface;

/**
 * @see https://adaptivecards.io/explorer/Action.Submit.html
 */
final class Submit extends Action implements SelectActionInterface
{
    public function __construct()
    {
        $this->options = [
            'type' => 'Action.Submit',
        ];
    }

    /**
     * @param array|string $data Sent with the values of the inputs; an array is merged with them, a string is sent on its own
     *
     * @return $this
     */
    public function data(array|string $data): static
    {
        $this->options['data'] = $data;

        return $this;
    }

    /**
     * @return $this
     */
    public function associatedInputs(AssociatedInputs $associatedInputs): static
    {
        $this->options['associatedInputs'] = $associatedInputs->value;

        return $this;
    }
}
