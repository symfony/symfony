<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A textarea field
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class TextareaField extends Field
{
    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        $content = $this->generator->escape($this->getDisplayedData());

        return $this->generator->contentTag('textarea', $content, array_merge(array(
            'id'    => $this->getId(),
            'name'  => $this->getName(),
            'rows'  => 4,
            'cols'  => 30,
        ), $attributes));
    }
}
