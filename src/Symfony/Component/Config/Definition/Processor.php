<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

/**
 * This class is the entry point for config normalization/merging/finalization.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Processor
{
    /**
     * Processes an array of configurations.
     *
     * @param NodeInterface $configTree The node tree describing the configuration
     * @param array         $configs    An array of configuration items to process
     *
     * @return array The processed configuration
     */
    public function process(NodeInterface $configTree, array $configs)
    {
        $configs = self::normalizeKeys($configs);

        $currentConfig = array();
        foreach ($configs as $config) {
            $config = $configTree->normalize($config);
            $currentConfig = $configTree->merge($currentConfig, $config);
        }

        return $configTree->finalize($currentConfig);
    }

    /**
     * Processes an array of configurations.
     *
     * @param ConfigurationInterface $configuration  The configuration class
     * @param array                  $configs        An array of configuration items to process
     *
     * @return array The processed configuration
     */
    public function processConfiguration(ConfigurationInterface $configuration, array $configs)
    {
        return $this->process($configuration->getConfigTreeBuilder()->buildTree(), $configs);
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
    static public function normalizeKeys(array $config)
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
     * @param array  $config A config array
     * @param string $key    The key to normalize
     * @param string $plural The plural form of the key if it is irregular
     *
     * @return array
     */
    static public function normalizeConfig($config, $key, $plural = null)
    {
        if (null === $plural) {
            $plural = $key.'s';
        }

        $values = array();
        if (isset($config[$plural])) {
            $values = $config[$plural];
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
