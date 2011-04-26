<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Extension\Core\DataTransformer\IntegerToLocalizedStringTransformer;

class IntegerType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->appendClientTransformer(new IntegerToLocalizedStringTransformer($options['precision'], $options['grouping'], $options['rounding_mode']));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            // default precision is locale specific (usually around 3)
            'precision' => null,
            'grouping' => false,
            // Integer cast rounds towards 0, so do the same when displaying fractions
            'rounding_mode' => IntegerToLocalizedStringTransformer::ROUND_DOWN,
        );
    }

    public function getParent(array $options)
    {
        return 'field';
    }

    public function getName()
    {
        return 'integer';
    }
}