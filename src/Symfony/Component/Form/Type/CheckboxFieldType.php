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
use Symfony\Component\Form\DataTransformer\BooleanToStringTransformer;
use Symfony\Component\Form\Renderer\Plugin\CheckedPlugin;

class CheckboxFieldType extends AbstractFieldType
{
    public function configure(FieldBuilder $builder, array $options)
    {
        $builder->setClientTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new CheckedPlugin())
            ->setRendererVar('value', $options['value']);
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'checkbox',
            'value' => '1',
        );
    }

    public function getParent(array $options)
    {
        return 'field';
    }

    public function getName()
    {
        return 'checkbox';
    }
}