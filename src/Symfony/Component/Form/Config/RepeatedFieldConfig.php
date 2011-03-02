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
use Symfony\Component\Form\ValueTransformer\ValueToDuplicatesTransformer;

class RepeatedFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldInterface $field, array $options)
    {
        $firstChild = clone $options['prototype'];
        $firstChild->setKey($options['first_key']);
        $firstChild->setPropertyPath($options['first_key']);

        $secondChild = clone $options['prototype'];
        $secondChild->setKey($options['second_key']);
        $secondChild->setPropertyPath($options['second_key']);

        $field->setValueTransformer(new ValueToDuplicatesTransformer(array(
                $options['first_key'],
                $options['second_key'],
            )))
            ->add($firstChild)
            ->add($secondChild);
    }

    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array(
            'template' => 'repeated',
            'first_key' => 'first',
            'second_key' => 'second',
        );

        // Lazy creation of the prototype
        if (!isset($options['prototype'])) {
            $defaultOptions['prototype'] = $this->getInstance('text');
        }

        return $defaultOptions;
    }

    public function getParent(array $options)
    {
        return 'form';
    }

    public function getIdentifier()
    {
        return 'repeated';
    }
}