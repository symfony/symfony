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
abstract class InputField extends Field
{
    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        return $this->generator->tag('input', array_merge(array(
            'id'          => $this->getId(),
            'name'        => $this->getName(),
            'value'       => $this->getDisplayedData(),
            'disabled'    => $this->isDisabled(),
        ), $attributes));
    }
}
