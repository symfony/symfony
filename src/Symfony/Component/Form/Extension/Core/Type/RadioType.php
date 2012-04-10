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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class RadioType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function getParent(array $options)
    {
        return 'checkbox';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'radio';
    }
}
