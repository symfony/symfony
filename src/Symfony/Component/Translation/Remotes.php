<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Remote\RemoteInterface;

final class Remotes
{
    private $remotes;

    /**
     * @param RemoteInterface[] $remotes
     */
    public function __construct(iterable $remotes)
    {
        $this->remotes = [];
        foreach ($remotes as $name => $remote) {
            $this->remotes[$name] = $remote;
        }
    }

    public function __toString(): string
    {
        return '['.implode(',', array_keys($this->remotes)).']';
    }

    public function has(string $name): bool
    {
        return isset($this->remotes[$name]);
    }

    public function get(string $name): RemoteInterface
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('Remote "%s" not found. Available: %s', $name, (string) $this));
        }

        return $this->remotes[$name];
    }

    public function keys(): array
    {
        return array_keys($this->remotes);
    }
}
