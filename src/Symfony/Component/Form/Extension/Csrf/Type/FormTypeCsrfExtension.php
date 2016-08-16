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
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderAdapter;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfTokenManagerAdapter;
use Symfony\Component\Form\Extension\Csrf\EventListener\CsrfValidationListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Util\ServerParams;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeCsrfExtension extends AbstractTypeExtension
{
    /**
     * @var CsrfTokenManagerInterface
     */
    private $defaultTokenManager;

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

    /**
     * @var ServerParams
     */
    private $serverParams;

    public function __construct($defaultTokenManager, $defaultEnabled = true, $defaultFieldName = '_token', TranslatorInterface $translator = null, $translationDomain = null, ServerParams $serverParams = null)
    {
        if ($defaultTokenManager instanceof CsrfProviderInterface) {
            $defaultTokenManager = new CsrfProviderAdapter($defaultTokenManager);
        } elseif (!$defaultTokenManager instanceof CsrfTokenManagerInterface) {
            throw new UnexpectedTypeException($defaultTokenManager, 'CsrfProviderInterface or CsrfTokenManagerInterface');
        }

        $this->defaultTokenManager = $defaultTokenManager;
        $this->defaultEnabled = $defaultEnabled;
        $this->defaultFieldName = $defaultFieldName;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->serverParams = $serverParams;
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
                $options['csrf_token_manager'],
                $options['csrf_token_id'] ?: ($builder->getName() ?: get_class($builder->getType()->getInnerType())),
                $options['csrf_message'],
                $this->translator,
                $this->translationDomain,
                $this->serverParams
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
            $tokenId = $options['csrf_token_id'] ?: ($form->getName() ?: get_class($form->getConfig()->getType()->getInnerType()));
            $data = (string) $options['csrf_token_manager']->getToken($tokenId);

            $csrfForm = $factory->createNamed($options['csrf_field_name'], 'Symfony\Component\Form\Extension\Core\Type\HiddenType', $data, array(
                'mapped' => false,
            ));

            $view->children[$options['csrf_field_name']] = $csrfForm->createView($view);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        // BC clause for the "intention" option
        $csrfTokenId = function (Options $options) {
            if (null !== $options['intention']) {
                @trigger_error('The form option "intention" is deprecated since version 2.8 and will be removed in 3.0. Use "csrf_token_id" instead.', E_USER_DEPRECATED);
            }

            return $options['intention'];
        };

        // BC clause for the "csrf_provider" option
        $csrfTokenManager = function (Options $options) {
            if ($options['csrf_provider'] instanceof CsrfTokenManagerInterface) {
                return $options['csrf_provider'];
            }

            return $options['csrf_provider'] instanceof CsrfTokenManagerAdapter
                ? $options['csrf_provider']->getTokenManager(false)
                : new CsrfProviderAdapter($options['csrf_provider']);
        };

        $defaultTokenManager = $this->defaultTokenManager;
        $csrfProviderNormalizer = function (Options $options, $csrfProvider) use ($defaultTokenManager) {
            if (null !== $csrfProvider) {
                @trigger_error('The form option "csrf_provider" is deprecated since version 2.8 and will be removed in 3.0. Use "csrf_token_manager" instead.', E_USER_DEPRECATED);

                return $csrfProvider;
            }

            return $defaultTokenManager;
        };

        $resolver->setDefaults(array(
            'csrf_protection' => $this->defaultEnabled,
            'csrf_field_name' => $this->defaultFieldName,
            'csrf_message' => 'The CSRF token is invalid. Please try to resubmit the form.',
            'csrf_token_manager' => $csrfTokenManager,
            'csrf_token_id' => $csrfTokenId,
            'csrf_provider' => null, // deprecated
            'intention' => null, // deprecated
        ));

        $resolver->setNormalizer('csrf_provider', $csrfProviderNormalizer);
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'Symfony\Component\Form\Extension\Core\Type\FormType';
    }
}
