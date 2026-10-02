<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Input;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Configurable keybindings manager.
 *
 * Maps action names to key identifiers, allowing customizable keybindings.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Keybindings
{
    /** @var array<string, string[]> */
    private array $bindings;

    private KeyParser $parser;

    /**
     * @param array<string, string[]> $bindings
     *
     * @throws InvalidArgumentException When a key id has no key, names an unknown modifier or an unknown key
     */
    public function __construct(array $bindings = [], ?KeyParser $parser = null)
    {
        $this->bindings = $bindings;
        $this->parser = $parser ?? new KeyParser();

        foreach ($bindings as $keyIds) {
            foreach ($keyIds as $keyId) {
                $this->parser->validateKeyId($keyId);
            }
        }
    }

    public function matches(string $data, string $action): bool
    {
        if (!isset($this->bindings[$action])) {
            return false;
        }

        foreach ($this->bindings[$action] as $keyId) {
            if ($this->parser->matches($data, $keyId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function getBindings(string $action): array
    {
        return $this->bindings[$action] ?? [];
    }

    /**
     * @return array<string, string[]>
     */
    public function all(): array
    {
        return $this->bindings;
    }

    public function setKittyProtocolActive(bool $active): void
    {
        $this->parser->setKittyProtocolActive($active);
    }

    /**
     * @internal
     */
    public function getParser(): KeyParser
    {
        return $this->parser;
    }
}
