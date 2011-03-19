<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FieldBuilder;

class TextFieldType extends AbstractFieldType
{
    public function configure(FieldBuilder $builder, array $options)
    {
        $builder->setRendererVar('max_length', $options['max_length']);
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'text',
        );
    }

    public function getParent(array $options)
    {
        return 'field';
    }

    public function getName()
    {
        return 'text';
    }
}