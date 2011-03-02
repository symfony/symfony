<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Config;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\FormFactoryInterface;

abstract class AbstractFieldConfig implements FieldConfigInterface
{
    private $factory;

    public function setFormFactory(FormFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    protected function getInstance($identifier, $key = null, array $options = array())
    {
        return $this->factory->getInstance($identifier, $key, $options);
    }

    public function configure(FieldInterface $field, array $options)
    {
    }

    public function getClassName()
    {
        return null;
    }

    public function getDefaultOptions(array $options)
    {
        return array();
    }
}