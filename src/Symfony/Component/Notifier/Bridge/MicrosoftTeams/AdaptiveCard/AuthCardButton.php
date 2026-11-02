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
 * @see https://adaptivecards.io/explorer/AuthCardButton.html
 */
final class AuthCardButton
{
    private array $options;

    public function __construct(string $type, string $value)
    {
        $this->options = [
            'type' => $type,
            'value' => $value,
        ];
    }

    /**
     * @return $this
     */
    public function title(string $title): static
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * @return $this
     */
    public function image(string $image): static
    {
        $this->options['image'] = $image;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
