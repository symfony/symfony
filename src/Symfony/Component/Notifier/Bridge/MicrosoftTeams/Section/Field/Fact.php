<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Section\Field;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Fact
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
    public function value(string $value): static
    {
        $this->options['value'] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
