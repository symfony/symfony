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

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class EnvPlaceholderParameterBag extends ParameterBag
{
    private $envPlaceholders = array();

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (0 === strpos($name, 'env(') && ')' === substr($name, -1) && 'env()' !== $name) {
            $env = substr($name, 4, -1);

            if (isset($this->envPlaceholders[$env])) {
                return $this->envPlaceholders[$env][0];
            }
            if (preg_match('/\W/', $env)) {
                throw new InvalidArgumentException(sprintf('Invalid %s name: only "word" characters are allowed.', $name));
            }

            if ($this->has($name)) {
                $defaultValue = parent::get($name);

                if (!is_scalar($defaultValue)) {
                    throw new RuntimeException(sprintf('The default value of an env() parameter must be scalar, but "%s" given to "%s".', gettype($defaultValue), $name));
                }
            }

            return $this->envPlaceholders[$env][] = sprintf('env_%s_%s', $env, md5($name.uniqid(mt_rand(), true)));
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
        $this->envPlaceholders = array_merge_recursive($this->envPlaceholders, $bag->getEnvPlaceholders());
    }
}
