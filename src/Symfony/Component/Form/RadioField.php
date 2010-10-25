<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * A radio field for selecting boolean values.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class RadioField extends ToggleField
{
    /**
     * {@inheritDoc}
     */
    public function __construct($key, array $options = array())
    {
        $options['type'] = 'radio';

        parent::__construct($key, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributes()
    {
        return array_merge(parent::getAttributes(), array(
            // TODO: should getName() be overridden instead?
            'name' => $this->getParent() ? $this->getParent()->getName() : $this->getName(),
        ));
    }
}
