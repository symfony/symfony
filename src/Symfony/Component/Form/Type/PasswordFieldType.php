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
use Symfony\Component\Form\Renderer\Plugin\PasswordValuePlugin;

class PasswordFieldType extends AbstractFieldType
{
    public function configure(FieldBuilder $builder, array $options)
    {
        $builder->addRendererPlugin(new PasswordValuePlugin($options['always_empty']));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'password',
            'always_empty' => true,
        );
    }

    public function getParent(array $options)
    {
        return 'text';
    }

    public function getName()
    {
        return 'password';
    }
}