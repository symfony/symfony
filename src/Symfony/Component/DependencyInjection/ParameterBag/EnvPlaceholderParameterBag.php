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

use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class EnvPlaceholderParameterBag extends ParameterBag
{
    private $envPlaceholders = array();
    private $resolveEnvReferences = false;

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
            if (preg_match('/\W/', $env)) {
                throw new InvalidArgumentException(sprintf('Invalid %s name: only "word" characters are allowed.', $name));
            }

            if ($this->has($name)) {
                $defaultValue = parent::get($name);

                if (null !== $defaultValue && !is_scalar($defaultValue)) {
                    throw new RuntimeException(sprintf('The default value of an env() parameter must be scalar or null, but "%s" given to "%s".', gettype($defaultValue), $name));
                }
            }

            $uniqueName = md5($name.uniqid(mt_rand(), true));
            $placeholder = sprintf('env_%s_%s', $env, $uniqueName);
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
     * {@inheritdoc}
     */
    public function resolve()
    {
        if ($this->resolved) {
            return;
        }
        parent::resolve();

        foreach ($this->envPlaceholders as $env => $placeholders) {
            if (!isset($this->parameters[$name = strtolower("env($env)")])) {
                continue;
            }
            if (is_numeric($default = $this->parameters[$name])) {
                $this->parameters[$name] = (string) $default;
            } elseif (null !== $default && !is_scalar($default)) {
                throw new RuntimeException(sprintf('The default value of env parameter "%s" must be scalar or null, %s given.', $env, gettype($default)));
            }
        }
    }

    /**
     * Replaces "%env(FOO)%" references by their placeholder, keeping regular "%parameters%" references as is.
     */
    public function resolveEnvReferences()
    {
        $this->resolveEnvReferences = true;
        try {
            $this->resolve();
        } finally {
            $this->resolveEnvReferences = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resolveString($value, array $resolving = array())
    {
        if ($this->resolveEnvReferences) {
            return preg_replace_callback('/%%|%(env\([^%\s]+\))%/', function ($match) { return isset($match[1]) ? $this->get($match[1]) : '%%'; }, $value);
        }

        return parent::resolveString($value, $resolving);
    }
}
