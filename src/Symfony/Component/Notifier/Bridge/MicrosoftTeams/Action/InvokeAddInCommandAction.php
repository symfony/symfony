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

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @see https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#invokeaddincommand-action
 */
final class InvokeAddInCommandAction implements ActionInterface
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
    public function addInId(string $addInId): static
    {
        $this->options['addInId'] = $addInId;

        return $this;
    }

    /**
     * @return $this
     */
    public function desktopCommandId(string $desktopCommandId): static
    {
        $this->options['desktopCommandId'] = $desktopCommandId;

        return $this;
    }

    /**
     * @return $this
     */
    public function initializationContext(array $context): static
    {
        $this->options['initializationContext'] = $context;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options + ['@type' => 'InvokeAddInCommand'];
    }
}
