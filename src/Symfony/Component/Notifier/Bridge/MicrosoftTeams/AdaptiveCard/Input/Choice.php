<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Input;

/**
 * @see https://adaptivecards.io/explorer/Input.Choice.html
 */
final class Choice
{
    private array $options;

    public function __construct(string $title, string $value)
    {
        $this->options = [
            'type' => 'Input.Choice',
            'title' => $title,
            'value' => $value,
        ];
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
