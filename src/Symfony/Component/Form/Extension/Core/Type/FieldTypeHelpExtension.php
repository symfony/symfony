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

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class FieldTypeHelpExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('help', $options['help']);
    }

    public function buildView(FormView $view, FormInterface $form)
    {
        $view->set('help', $form->getAttribute('help'));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'help' => null,
        );
    }

    public function getExtendedType()
    {
        return 'field';
    }
}
