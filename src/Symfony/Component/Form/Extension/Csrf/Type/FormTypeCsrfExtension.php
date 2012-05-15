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
use Symfony\Component\Form\Extension\Csrf\EventListener\EnsureCsrfFieldListener;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

class FormTypeCsrfExtension extends AbstractTypeExtension
{
    private $enabled;
    private $fieldName;

    public function __construct($enabled = true, $fieldName = '_token')
    {
        $this->enabled = $enabled;
        $this->fieldName = $fieldName;
    }

    /**
     * Adds a CSRF field to the form when the CSRF protection is enabled.
     *
     * @param FormBuilder $builder The form builder
     * @param array       $options The options
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        if (!$options['csrf_protection']) {
            return;
        }

        $listener = new EnsureCsrfFieldListener(
            $builder->getFormFactory(),
            $options['csrf_field_name'],
            $options['intention'],
            $options['csrf_provider']
        );

        // use a low priority so higher priority listeners don't remove the field
        $builder
            ->setAttribute('csrf_field_name', $options['csrf_field_name'])
            ->addEventListener(FormEvents::PRE_SET_DATA, array($listener, 'ensureCsrfField'), -10)
            ->addEventListener(FormEvents::PRE_BIND, array($listener, 'ensureCsrfField'), -10)
        ;
    }

    /**
     * Removes CSRF fields from all the form views except the root one.
     *
     * @param FormView      $view The form view
     * @param FormInterface $form The form
     */
    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
        if ($view->hasParent() && $form->hasAttribute('csrf_field_name')) {
            $name = $form->getAttribute('csrf_field_name');

            if (isset($view[$name])) {
                unset($view[$name]);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'csrf_protection'   => $this->enabled,
            'csrf_field_name'   => $this->fieldName,
            'csrf_provider'     => null,
            'intention'         => 'unknown',
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getExtendedType()
    {
        return 'form';
    }
}
