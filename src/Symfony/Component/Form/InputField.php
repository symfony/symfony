<?php

namespace Symfony\Component\Form;

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
