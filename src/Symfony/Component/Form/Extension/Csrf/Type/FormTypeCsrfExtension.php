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
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\Extension\Csrf\EventListener\CsrfValidationListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeCsrfExtension extends AbstractTypeExtension
{
    /**
     * @var CsrfProviderInterface
     */
    private $defaultCsrfProvider;

    /**
     * @var bool
     */
    private $defaultEnabled;

    /**
     * @var string
     */
    private $defaultFieldName;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var null|string
     */
    private $translationDomain;

    public function __construct(CsrfProviderInterface $defaultCsrfProvider, $defaultEnabled = true, $defaultFieldName = '_token', TranslatorInterface $translator = null, $translationDomain = null)
    {
        $this->defaultCsrfProvider = $defaultCsrfProvider;
        $this->defaultEnabled = $defaultEnabled;
        $this->defaultFieldName = $defaultFieldName;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    /**
     * Adds a CSRF field to the form when the CSRF protection is enabled.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array                $options The options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options['csrf_protection']) {
            return;
        }

        $builder
            ->setAttribute('csrf_factory', $builder->getFormFactory())
            ->addEventSubscriber(new CsrfValidationListener(
                $options['csrf_field_name'],
                $options['csrf_provider'],
                $options['intention'] ?: ($builder->getName() ?: get_class($builder->getType()->getInnerType())),
                $options['csrf_message'],
                $this->translator,
                $this->translationDomain
            ))
        ;
    }

    /**
     * Adds a CSRF field to the root form view.
     *
     * @param FormView      $view    The form view
     * @param FormInterface $form    The form
     * @param array         $options The options
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['csrf_protection'] && !$view->parent && $options['compound']) {
            $factory = $form->getConfig()->getAttribute('csrf_factory');
            $intention = $options['intention'] ?: ($form->getName() ?: get_class($form->getConfig()->getType()->getInnerType()));
            $data = $options['csrf_provider']->generateCsrfToken($intention);

            $csrfForm = $factory->createNamed($options['csrf_field_name'], 'hidden', $data, array(
                'mapped' => false,
            ));

            $view->children[$options['csrf_field_name']] = $csrfForm->createView($view);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'csrf_protection' => $this->defaultEnabled,
            'csrf_field_name' => $this->defaultFieldName,
            'csrf_provider' => $this->defaultCsrfProvider,
            'csrf_message' => 'The CSRF token is invalid. Please try to resubmit the form.',
            'intention' => null,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'form';
    }
}
