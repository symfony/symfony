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
 * A text input field.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class TextField extends InputField
{
    /**
     * {@inheritDoc}
     */
    public function __construct($key, array $options = array())
    {
        $options['type'] = 'text';

        parent::__construct($key, $options);
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('max_length');
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributes()
    {
        return array_merge(parent::getAttributes(), array(
            'maxlength'   => $this->getOption('max_length'),
        ));
    }
}
