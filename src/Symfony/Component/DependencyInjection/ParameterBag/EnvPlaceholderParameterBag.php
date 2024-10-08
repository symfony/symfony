<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\ParameterBag;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class EnvPlaceholderParameterBag extends ParameterBag
{
    private string $envPlaceholderUniquePrefix;
    private array $envPlaceholders = [];
    private array $unusedEnvPlaceholders = [];
    private array $providedTypes = [];

    private static int $counter = 0;

    public function get(string $name): array|bool|string|int|float|\UnitEnum|null
    {
        if (str_starts_with($name, 'env(') && str_ends_with($name, ')') && 'env()' !== $name) {
            $env = substr($name, 4, -1);

            if (isset($this->envPlaceholders[$env])) {
                foreach ($this->envPlaceholders[$env] as $placeholder) {
                    return $placeholder; // return first result
                }
            }
            if (isset($this->unusedEnvPlaceholders[$env])) {
                foreach ($this->unusedEnvPlaceholders[$env] as $placeholder) {
                    return $placeholder; // return first result
                }
            }
            if (!preg_match('/^(?:[-.\w\\\\]*+:)*+\w*+$/', $env)) {
                throw new InvalidArgumentException(sprintf('The given env var name "%s" contains invalid characters (allowed characters: letters, digits, underscores, backslashes and colons).', $name));
            }
            if ($this->has($name) && null !== ($defaultValue = parent::get($name)) && !\is_string($defaultValue)) {
                throw new RuntimeException(sprintf('The default value of an env() parameter must be a string or null, but "%s" given to "%s".', get_debug_type($defaultValue), $name));
            }

            $uniqueName = hash('xxh128', $name.'_'.self::$counter++);
            $placeholder = sprintf('%s_%s_%s', $this->getEnvPlaceholderUniquePrefix(), strtr($env, ':-.\\', '____'), $uniqueName);
            $this->envPlaceholders[$env][$placeholder] = $placeholder;

            return $placeholder;
        }

        return parent::get($name);
    }

    /**
     * Gets the common env placeholder prefix for env vars created by this bag.
     */
    public function getEnvPlaceholderUniquePrefix(): string
    {
        if (!isset($this->envPlaceholderUniquePrefix)) {
            $reproducibleEntropy = unserialize(serialize($this->parameters));
            array_walk_recursive($reproducibleEntropy, function (&$v) { $v = null; });
            $this->envPlaceholderUniquePrefix = 'env_'.substr(hash('xxh128', serialize($reproducibleEntropy)), -16);
        }

        return $this->envPlaceholderUniquePrefix;
    }

    /**
     * Returns the map of env vars used in the resolved parameter values to their placeholders.
     *
     * @return string[][] A map of env var names to their placeholders
     */
    public function getEnvPlaceholders(): array
    {
        return $this->envPlaceholders;
    }

    public function getUnusedEnvPlaceholders(): array
    {
        return $this->unusedEnvPlaceholders;
    }

    public function clearUnusedEnvPlaceholders(): void
    {
        $this->unusedEnvPlaceholders = [];
    }

    /**
     * Merges the env placeholders of another EnvPlaceholderParameterBag.
     */
    public function mergeEnvPlaceholders(self $bag): void
    {
        if ($newPlaceholders = $bag->getEnvPlaceholders()) {
            $this->envPlaceholders += $newPlaceholders;

            foreach ($newPlaceholders as $env => $placeholders) {
                $this->envPlaceholders[$env] += $placeholders;
            }
        }

        if ($newUnusedPlaceholders = $bag->getUnusedEnvPlaceholders()) {
            $this->unusedEnvPlaceholders += $newUnusedPlaceholders;

            foreach ($newUnusedPlaceholders as $env => $placeholders) {
                $this->unusedEnvPlaceholders[$env] += $placeholders;
            }
        }
    }

    /**
     * Maps env prefixes to their corresponding PHP types.
     */
    public function setProvidedTypes(array $providedTypes): void
    {
        $this->providedTypes = $providedTypes;
    }

    /**
     * Gets the PHP types corresponding to env() parameter prefixes.
     *
     * @return string[][]
     */
    public function getProvidedTypes(): array
    {
        return $this->providedTypes;
    }

    public function resolve(): void
    {
        if ($this->resolved) {
            return;
        }
        parent::resolve();

        foreach ($this->envPlaceholders as $env => $placeholders) {
            if ($this->has($name = "env($env)") && null !== ($default = $this->parameters[$name]) && !\is_string($default)) {
                throw new RuntimeException(sprintf('The default value of env parameter "%s" must be a string or null, "%s" given.', $env, get_debug_type($default)));
            }
        }
    }
}
