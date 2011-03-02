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

class BirthdayFieldConfig extends AbstractFieldConfig
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

    public function getIdentifier()
    {
        return 'birthday';
    }
}