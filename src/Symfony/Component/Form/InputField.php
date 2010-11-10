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
 * Base class for all low-level fields represented by input tags
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class InputField extends Field
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addRequiredOption('type');
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributes()
    {
        return array_merge(parent::getAttributes(), array(
            'id'       => $this->getId(),
            'name'     => $this->getName(),
            'value'    => $this->getDisplayedData(),
            'disabled' => $this->isDisabled(),
            'type'     => $this->getOption('type'),
        ));
    }
}
