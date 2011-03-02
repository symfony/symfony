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

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;
use Symfony\Component\Form\Renderer\Plugin\CheckedPlugin;

class CheckboxFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldInterface $field, array $options)
    {
        $field->setValueTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new CheckedPlugin($field))
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

    public function getIdentifier()
    {
        return 'checkbox';
    }
}