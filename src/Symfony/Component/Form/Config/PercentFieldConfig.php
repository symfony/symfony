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

class PercentFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldInterface $field, array $options)
    {
        $field->setValueTransformer(new PercentToLocalizedStringTransformer(array(
                'precision' => $options['precision'],
                'type' => $options['type'],
            )));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'percent',
            'precision' => 0,
            'type' => 'fractional',
        );
    }

    public function getParent(array $options)
    {
        return 'field';
    }

    public function getIdentifier()
    {
        return 'percent';
    }
}