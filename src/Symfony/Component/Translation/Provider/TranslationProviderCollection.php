<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Provider;

use Symfony\Component\Translation\Exception\InvalidArgumentException;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * @experimental in 5.3
 */
final class TranslationProviderCollection
{
    private $providers;

    /**
     * @param array<string, ProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        $this->providers = [];
        foreach ($providers as $name => $provider) {
            $this->providers[$name] = $provider;
        }
    }

    public function __toString(): string
    {
        return '['.implode(',', array_keys($this->providers)).']';
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    public function get(string $name): ProviderInterface
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('Provider "%s" not found. Available: "%s".', $name, (string) $this));
        }

        return $this->providers[$name];
    }

    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
