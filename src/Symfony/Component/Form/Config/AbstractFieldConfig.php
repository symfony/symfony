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

    public function __construct(FormFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    protected function getFormFactory()
    {
        return $this->factory;
    }

    protected function getInstance($identifier, $name = null, array $options = array())
    {
        return $this->factory->getInstance($identifier, $name, $options);
    }

    public function configure(FieldInterface $field, array $options)
    {
    }

    public function createInstance($name)
    {
        return null;
    }

    public function getDefaultOptions(array $options)
    {
        return array();
    }

    public function getIdentifier()
    {
        return get_class($this);
    }
}