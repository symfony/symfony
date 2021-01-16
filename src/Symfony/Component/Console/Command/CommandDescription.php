<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

/**
 * Interface for command reacting to signal.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class CommandDescription
{
    private $name;
    private $aliases;
    private $description;
    private $hidden;
    private $enabled;

    public function __construct(string $name, string $description = '', array $aliases = [], bool $hidden = false, bool $enabled = true)
    {
        $this->name = $name;
        $this->aliases = $aliases;
        $this->description = $description;
        $this->hidden = $hidden;
        $this->enabled = $enabled;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }
}
