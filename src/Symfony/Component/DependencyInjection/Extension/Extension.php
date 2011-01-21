<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Extension;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Extension is a helper class that helps organize extensions better.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Extension implements ExtensionInterface
{
    /**
     * Loads a specific configuration.
     *
     * @param string  $tag           The tag name
     * @param array   $config        An array of configuration values
     * @param ContainerBuilder $configuration A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load($tag, array $config, ContainerBuilder $configuration)
    {
        if (!method_exists($this, $method = $tag.'Load')) {
            throw new \InvalidArgumentException(sprintf('The tag "%s:%s" is not defined in the "%s" extension.', $this->getAlias(), $tag, $this->getAlias()));
        }

        $this->$method($config, $configuration);
    }

    /**
     * This method normalizes keys between the different configuration formats
     *
     * Namely, you mostly have foo_bar in YAML while you have foo-bar in XML.
     * After running this method, all keys are normalized to foo_bar.
     *
     * If you have a mixed key like foo-bar_moo, it will not be altered.
     * The key will also not be altered if the target key already exists.
     *
     * @param array $config
     *
     * @return array the config with normalized keys
     */
    public static function normalizeKeys(array $config)
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = self::normalizeKeys($value);
            }

            if (false !== strpos($key, '-') && false === strpos($key, '_') && !array_key_exists($normalizedKey = str_replace('-', '_', $key), $config)) {
                $config[$normalizedKey] = $config[$key];
                unset($config[$key]);
            }
        }

        return $config;
    }

    /**
     * Normalizes a configuration entry.
     *
     * This method returns a normalize configuration array for a given key
     * to remove the differences due to the original format (YAML and XML mainly).
     *
     * Here is an example.
     *
     * The configuration is XML:
     *
     * <twig:extension id="twig.extension.foo" />
     * <twig:extension id="twig.extension.bar" />
     *
     * And the same configuration in YAML:
     *
     * twig.extensions: ['twig.extension.foo', 'twig.extension.bar']
     *
     * @param array A config array
     * @param key   The key to normalize
     */
    public static function normalizeConfig($config, $key)
    {
        $values = array();
        if (isset($config[$key.'s'])) {
            $values = $config[$key.'s'];
        } elseif (isset($config[$key])) {
            if (is_string($config[$key]) || !is_int(key($config[$key]))) {
                // only one
                $values = array($config[$key]);
            } else {
                $values = $config[$key];
            }
        }

        return $values;
    }
}
