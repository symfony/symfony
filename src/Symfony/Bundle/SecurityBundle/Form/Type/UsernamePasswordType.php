<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Form type for use with the Security component's form-based authentication
 * listener.
 *
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class UsernamePasswordType extends AbstractType
{
    private $authenticationUtils;
    private $urlGenerator;

    public function __construct(AuthenticationUtils $authenticationUtils, UrlGeneratorInterface $urlGenerator)
    {
        $this->authenticationUtils = $authenticationUtils;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add($options['username_field_name'], 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->add($options['password_field_name'], 'Symfony\Component\Form\Extension\Core\Type\PasswordType')
            ->add($options['target_path_field_name'],  'Symfony\Component\Form\Extension\Core\Type\HiddenType')
        ;

        /* Note: since the Security component's form login listener intercepts
         * the POST request, this form will never really be bound to the
         * request; however, we can match the expected behavior by checking the
         * session for an authentication error and last username.
         */
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            if (null !== $error = $this->authenticationUtils->getLastAuthenticationError()) {
                $event->getForm()->addError(new FormError($error->getMessage()));
            }

            $event->setData(array_replace((array) $event->getData(), array(
                $options['username_field_name'] => $this->authenticationUtils->getLastUsername(),
            )));
        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /* Note: the form's csrf_token_id must correspond to that for the form login
         * listener in order for the CSRF token to validate successfully.
         */

        $resolver->setDefaults(array(
            'target_firewall' => null,
            'action' => function (Options $options) {
                $action = $this->getFirewallOption($options['target_firewall'], 'check_path', '/login_check');

                if ('/' === $action[0]) {
                    return $action;
                }

                return $this->urlGenerator->generate($action);
            },
            'username_field_name' => function (Options $options) {
                return $this->getFirewallOption($options['target_firewall'], 'username_parameter', '_username');
            },
            'password_field_name' => function (Options $options) {
                return $this->getFirewallOption($options['target_firewall'], 'password_parameter', '_password');
            },
            'target_path_field_name' => function (Options $options) {
                return $this->getFirewallOption($options['target_firewall'], 'target_path_parameter', '_target_path');
            },
            'csrf_field_name' => function (Options $options) {
                return $this->getFirewallOption($options['target_firewall'], 'csrf_parameter', '_csrf_token');
            },
            'csrf_token_id' => function (Options $options) {
                return $this->getFirewallOption($options['target_firewall'], 'csrf_token_id', 'authenticate');
            },
        ));
    }

    private function getFirewallOption($firewallName, $optionName, $default)
    {
        if (null === $firewallName) {
            return $default;
        }

        if (null === $config = $this->configRegistry->get($firewallName)) {
            throw new InvalidOptionsException(sprintf('The firewall "%s" does not exist.', $firewallName));
        }

        return $config->getOption($optionName);
    }
}
