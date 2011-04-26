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
use Symfony\Component\Locale\Locale;

class LocaleType extends AbstractType
{
    public function getDefaultOptions(array $options)
    {
        return array(
            'choices' => Locale::getDisplayLocales(\Locale::getDefault()),
        );
    }

    public function getParent(array $options)
    {
        return 'choice';
    }

    public function getName()
    {
        return 'locale';
    }
}