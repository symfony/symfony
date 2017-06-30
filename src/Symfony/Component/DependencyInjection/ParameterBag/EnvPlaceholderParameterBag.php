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

use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class EnvPlaceholderParameterBag extends ParameterBag
{
    private $envPlaceholders = array();
    private $secretPlaceholders = array();

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (0 === strpos($name, 'env(') && ')' === substr($name, -1) && 'env()' !== $name) {
            return $this->doGet(false, $name, substr($name, 4, -1));
        }

        if (0 === strpos($name, 'secret(') && ')' === substr($name, -1) && 'secret()' !== $name) {
            return $this->doGet(true, $name, substr($name, 7, -1));
        }

        return parent::get($name);
    }

    private function doGet($secret, $name, $value)
    {
        if (($secret && isset($this->secretPlaceholders[$value])) || isset($this->envPlaceholders[$value])) {
            $placeholders = $secret ? $this->secretPlaceholders[$value] : $this->envPlaceholders[$value];
            foreach ($placeholders as $placeholder) {
                return $placeholder; // return first result
            }
        }
        if (preg_match($secret ? '/[^a-zA-Z0-9)(~\.:_\/\\-]/' : '/\W/', $value)) {
            throw new InvalidArgumentException(sprintf('Invalid %s name: only "%s" characters are allowed.', $name, $secret ? '[a-zA-Z0-9_\/\\-]' : 'word'));
        }

        if ($this->has($name)) {
            $defaultValue = parent::get($name);

            if (null !== $defaultValue && !is_scalar($defaultValue)) {
                throw new RuntimeException(sprintf('The default value of an %s() parameter must be scalar or null, but "%s" given to "%s".', $secret ? 'secret' : 'env', gettype($defaultValue), $name));
            }
        }

        $uniqueName = md5($name.uniqid(mt_rand(), true));
        $placeholder = sprintf('%s_%s_%s', $secret ? 'secret' : 'env', $value, $uniqueName);
        $secret ? $this->secretPlaceholders[$value][$placeholder] = $placeholder : $this->envPlaceholders[$value][$placeholder] = $placeholder;

        return $placeholder;
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
     * Returns the map of secret vars used in the resolved parameter values to their placeholders.
     *
     * @return string[][] A map of env var names to their placeholders
     */
    public function getSecretPlaceholders()
    {
        return $this->secretPlaceholders;
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
     * Merges the secret placeholders of another EnvPlaceholderParameterBag.
     */
    public function mergeSecretPlaceholders(self $bag)
    {
        if ($newPlaceholders = $bag->getSecretPlaceholders()) {
            $this->secretPlaceholders += $newPlaceholders;

            foreach ($newPlaceholders as $secret => $placeholders) {
                $this->secretPlaceholders[$secret] += $placeholders;
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

        $this->doResolve($this->envPlaceholders, 'env');
        $this->doResolve($this->secretPlaceholders, 'secret');
    }

    private function doResolve(array $placeholderList, $type)
    {
        foreach ($placeholderList as $envOrSecret => $placeholders) {
            if (!isset($this->parameters[$name = strtolower("$type($envOrSecret)")])) {
                continue;
            }
            if (is_numeric($default = $this->parameters[$name])) {
                $this->parameters[$name] = (string) $default;
            } elseif (null !== $default && !is_scalar($default)) {
                throw new RuntimeException(sprintf('The default value of %s parameter "%s" must be scalar or null, %s given.', $type, $envOrSecret, gettype($default)));
            }
        }
    }
}
