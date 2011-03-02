<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Config\FieldConfigInterface;

class FormFactory implements FormFactoryInterface
{
    public function addConfig(FieldConfigInterface $config)
    {
        $this->configs[$config->getIdentifier()] = $config;

        $config->setFormFactory($this);
    }

    public function getInstance($identifier, $key = null, array $options = array())
    {
        $className = null;
        $hierarchy = array();

        while (null !== $identifier) {
            // TODO check if identifier exists
            $config = $this->configs[$identifier];
            array_unshift($hierarchy, $config);
            $className = $className ?: $config->getClassName();
            $options = array_merge($config->getDefaultOptions($options), $options);
            $identifier = $config->getParent($options);
        }

        // TODO check if className is set

        $instance = new $className($key);

        foreach ($hierarchy as $config) {
            $config->configure($instance, $options);
        }

        return $instance;
    }
}