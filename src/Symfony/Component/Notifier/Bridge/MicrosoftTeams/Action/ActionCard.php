<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\InputInterface;

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @see https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#actioncard-action
 */
final class ActionCard implements ActionInterface
{
    private array $options = [];

    /**
     * @return $this
     */
    public function name(string $name): static
    {
        $this->options['name'] = $name;

        return $this;
    }

    /**
     * @return $this
     */
    public function input(InputInterface $inputAction): static
    {
        $this->options['inputs'][] = $inputAction->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function action(ActionCardCompatibleActionInterface $action): static
    {
        $this->options['actions'][] = $action->toArray();

        return $this;
    }

    public function toArray(): array
    {
        return $this->options + ['@type' => 'ActionCard'];
    }
}
