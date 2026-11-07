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
 * @see https://adaptivecards.io/explorer/Authentication.html
 */
final class Authentication
{
    private array $options = ['type' => 'Authentication'];

    /**
     * @return $this
     */
    public function text(string $text): static
    {
        $this->options['text'] = $text;

        return $this;
    }

    /**
     * @return $this
     */
    public function connectionName(string $connectionName): static
    {
        $this->options['connectionName'] = $connectionName;

        return $this;
    }

    /**
     * @return $this
     */
    public function tokenExchangeResource(TokenExchangeResource $tokenExchangeResource): static
    {
        $this->options['tokenExchangeResource'] = $tokenExchangeResource->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function button(AuthCardButton $button): static
    {
        $this->options['buttons'][] = $button->toArray();

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
