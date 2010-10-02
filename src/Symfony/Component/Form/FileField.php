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
 * A file field to upload files.
 */
class FileField extends InputField
{
    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        return parent::render(array_merge(array(
            'type' => 'file',
        ), $attributes));
    }

    /**
     * {@inheritDoc}
     */
    public function isMultipart()
    {
        return true;
    }
}
