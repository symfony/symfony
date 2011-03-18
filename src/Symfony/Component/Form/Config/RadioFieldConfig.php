<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Config;

use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\DataTransformer\BooleanToStringTransformer;
use Symfony\Component\Form\Renderer\Plugin\CheckedPlugin;
use Symfony\Component\Form\Renderer\Plugin\ParentNamePlugin;

class RadioFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldBuilder $builder, array $options)
    {
        $builder->setDataTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new CheckedPlugin())
            ->addRendererPlugin(new ParentNamePlugin())
            ->setRendererVar('value', $options['value']);
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'radio',
            'value' => null,
        );
    }

    public function getParent(array $options)
    {
        return 'field';
    }

    public function getIdentifier()
    {
        return 'radio';
    }
}