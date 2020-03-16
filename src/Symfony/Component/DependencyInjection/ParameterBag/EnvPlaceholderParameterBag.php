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
    private $envPlaceholders = [];
    private $providedTypes = [];

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (0 === strpos($name, 'env(') && ')' === substr($name, -1) && 'env()' !== $name) {
            $env = substr($name, 4, -1);

            if (isset($this->envPlaceholders[$env])) {
                foreach ($this->envPlaceholders[$env] as $placeholder) {
                    return $placeholder; // return first result
                }
            }
            if (!preg_match('/^(?:\w++:)*+\w++$/', $env)) {
                throw new InvalidArgumentException(sprintf('Invalid "%s" name: only "word" characters are allowed.', $name));
            }

            if ($this->has($name)) {
                $defaultValue = parent::get($name);

                if (null !== $defaultValue && !is_scalar($defaultValue)) {
                    throw new RuntimeException(sprintf('The default value of an env() parameter must be scalar or null, but "%s" given to "%s".', \gettype($defaultValue), $name));
                }
            }

            $uniqueName = md5($name.uniqid(mt_rand(), true));
            $placeholder = sprintf('env_%s_%s', str_replace(':', '_', $env), $uniqueName);
            $this->envPlaceholders[$env][$placeholder] = $placeholder;

            return $placeholder;
        }

        return parent::get($name);
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
                $this->parameters[$name] = (string) $default;
            } elseif (null !== $default && !is_scalar($default)) {
                throw new RuntimeException(sprintf('The default value of env parameter "%s" must be scalar or null, "%s" given.', $env, \gettype($default)));
            }
        }
    }
}
