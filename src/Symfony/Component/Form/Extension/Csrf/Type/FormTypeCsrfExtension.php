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
use Symfony\Component\Form\Extension\Csrf\EventListener\CsrfValidationListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Security\Csrf\CsrfTokenGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeCsrfExtension extends AbstractTypeExtension
{
    /**
     * @var CsrfTokenGeneratorInterface
     */
    private $defaultTokenGenerator;

    /**
     * @var Boolean
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

    public function __construct(CsrfTokenGeneratorInterface $defaultTokenGenerator, $defaultEnabled = true, $defaultFieldName = '_token', TranslatorInterface $translator = null, $translationDomain = null)
    {
        $this->defaultTokenGenerator = $defaultTokenGenerator;
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
            ->addEventSubscriber(new CsrfValidationListener(
                $options['csrf_field_name'],
                $options['csrf_token_generator'],
                $options['csrf_token_id'],
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
            $factory = $form->getConfig()->getFormFactory();
            $data = $options['csrf_token_generator']->generateCsrfToken($options['csrf_token_id']);

            $csrfForm = $factory->createNamed($options['csrf_field_name'], 'hidden', $data, array(
                'mapped' => false,
            ));

            $view->children[$options['csrf_field_name']] = $csrfForm->createView($view);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        // BC clause for the "intention" option
        $csrfTokenId = function (Options $options) {
            return $options['intention'];
        };

        // BC clause for the "csrf_provider" option
        $csrfTokenGenerator = function (Options $options) {
            return $options['csrf_provider'];
        };

        $resolver->setDefaults(array(
            'csrf_protection'      => $this->defaultEnabled,
            'csrf_field_name'      => $this->defaultFieldName,
            'csrf_message'         => 'The CSRF token is invalid. Please try to resubmit the form.',
            'csrf_token_generator' => $csrfTokenGenerator,
            'csrf_token_id'        => $csrfTokenId,
            'csrf_provider'        => $this->defaultTokenGenerator,
            'intention'            => 'unknown',
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function getExtendedType()
    {
        return 'form';
    }
}
