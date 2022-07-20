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
    private $envPlaceholderUniquePrefix;
    private $envPlaceholders = [];
    private $unusedEnvPlaceholders = [];
    private $providedTypes = [];

    private static $counter = 0;

    /**
     * {@inheritdoc}
     */
    public function get($name)
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
            if (!preg_match('/^(?:\w*+:)*+\w++$/', $env)) {
                throw new InvalidArgumentException(sprintf('Invalid "%s" name: only "word" characters are allowed.', $name));
            }

            if ($this->has($name)) {
                $defaultValue = parent::get($name);

                if (null !== $defaultValue && !\is_scalar($defaultValue)) { // !is_string in 5.0
                    // throw new RuntimeException(sprintf('The default value of an env() parameter must be a string or null, but "%s" given to "%s".', \gettype($defaultValue), $name));
                    throw new RuntimeException(sprintf('The default value of an env() parameter must be scalar or null, but "%s" given to "%s".', \gettype($defaultValue), $name));
                } elseif (\is_scalar($defaultValue) && !\is_string($defaultValue)) {
                    @trigger_error(sprintf('A non-string default value of an env() parameter is deprecated since 4.3, cast "%s" to string instead.', $name), \E_USER_DEPRECATED);
                }
            }

            $uniqueName = md5($name.'_'.self::$counter++);
            $placeholder = sprintf('%s_%s_%s', $this->getEnvPlaceholderUniquePrefix(), str_replace(':', '_', $env), $uniqueName);
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
        if (null === $this->envPlaceholderUniquePrefix) {
            $reproducibleEntropy = unserialize(serialize($this->parameters));
            array_walk_recursive($reproducibleEntropy, function (&$v) { $v = null; });
            $this->envPlaceholderUniquePrefix = 'env_'.substr(md5(serialize($reproducibleEntropy)), -16);
        }

        return $this->envPlaceholderUniquePrefix;
    }

    /**
     * Returns the map of env vars used in the resolved parameter values to their placeholders.
     *
     * @return string[][] A map of env var names to their placeholders
     */
    public function getEnvPlaceholders()
    {
        return $this->envPlaceholders;
    }

    public function getUnusedEnvPlaceholders(): array
    {
        return $this->unusedEnvPlaceholders;
    }

    public function clearUnusedEnvPlaceholders()
    {
        $this->unusedEnvPlaceholders = [];
    }

    /**
     * Merges the env placeholders of another EnvPlaceholderParameterBag.
     */
    public function mergeEnvPlaceholders(self $bag)
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
    public function setProvidedTypes(array $providedTypes)
    {
        $this->providedTypes = $providedTypes;
    }

    /**
     * Gets the PHP types corresponding to env() parameter prefixes.
     *
     * @return string[][]
     */
    public function getProvidedTypes()
    {
        return $this->providedTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve()
    {
        if ($this->resolved) {
            return;
        }
        parent::resolve();

        foreach ($this->envPlaceholders as $env => $placeholders) {
            if (!$this->has($name = "env($env)")) {
                continue;
            }
            if (is_numeric($default = $this->parameters[$name])) {
                if (!\is_string($default)) {
                    @trigger_error(sprintf('A non-string default value of env parameter "%s" is deprecated since 4.3, cast it to string instead.', $env), \E_USER_DEPRECATED);
                }
                $this->parameters[$name] = (string) $default;
            } elseif (null !== $default && !\is_scalar($default)) { // !is_string in 5.0
                // throw new RuntimeException(sprintf('The default value of env parameter "%s" must be a string or null, "%s" given.', $env, \gettype($default)));
                throw new RuntimeException(sprintf('The default value of env parameter "%s" must be scalar or null, "%s" given.', $env, \gettype($default)));
            } elseif (\is_scalar($default) && !\is_string($default)) {
                @trigger_error(sprintf('A non-string default value of env parameter "%s" is deprecated since 4.3, cast it to string instead.', $env), \E_USER_DEPRECATED);
            }
        }
    }
}
