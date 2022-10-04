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

use Symfony\Component\Notifier\Exception\InvalidArgumentException;

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @see https://docs.microsoft.com/en-us/outlook/actionable-messages/message-card-reference#openuri-action
 */
final class OpenUriAction implements ActionCardCompatibleActionInterface
{
    private const OPERATING_SYSTEMS = [
        'android',
        'default',
        'iOS',
        'windows',
    ];

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
    public function target(string $uri, string $os = 'default'): static
    {
        if (!\in_array($os, self::OPERATING_SYSTEMS)) {
            throw new InvalidArgumentException(sprintf('Supported operating systems for "%s" method are: "%s".', __METHOD__, implode('", "', self::OPERATING_SYSTEMS)));
        }

        $this->options['targets'][] = ['os' => $os, 'uri' => $uri];

        return $this;
    }

    public function toArray(): array
    {
        return $this->options + ['@type' => 'OpenUri'];
    }
}
