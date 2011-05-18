<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Csrf\Type;

use Symfony\Component\Form\AbstractTypeExtension;

class ChoiceTypeCsrfExtension extends AbstractTypeExtension
{
    public function getDefaultOptions(array $options)
    {
        return array('csrf_protection' => false);
    }

    public function getExtendedType()
    {
        return 'choice';
    }
}
