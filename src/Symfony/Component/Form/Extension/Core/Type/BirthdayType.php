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

class BirthdayType extends AbstractType
{
    public function getDefaultOptions(array $options)
    {
        return array(
            'years' => range(date('Y') - 120, date('Y')),
        );
    }

    public function getParent(array $options)
    {
        return 'date';
    }

    public function getName()
    {
        return 'birthday';
    }
}