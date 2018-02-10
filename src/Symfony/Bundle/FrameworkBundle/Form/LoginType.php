<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Form;

use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Translation\TranslatorInterface;

class LoginType extends AbstractType
{
    private $requestStack;
    private $firewallMap;
    private $authenticationUtils;
    private $urlGenerator;
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(RequestStack $requestStack, FirewallMap $firewallMap, AuthenticationUtils $authenticationUtils, UrlGeneratorInterface $urlGenerator, TranslatorInterface $translator)
    {
        $this->requestStack = $requestStack;
        $this->firewallMap = $firewallMap;
        $this->authenticationUtils = $authenticationUtils;
        $this->urlGenerator = $urlGenerator;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $firewallConfig = $this->firewallMap->getFirewallConfig($this->requestStack->getCurrentRequest());
        if (!$firewallConfig || !$firewallConfig->isSecurityEnabled()) {
            throw new \LogicException('You cannot use LoginType if security is not enabled');
        }

        $firewallOptions = $firewallConfig->getOptions()['form_login'];

        $builder
            ->add($firewallOptions['username_parameter'], TextType::class, ['label' => 'label.username']) // Label should be configurable
            ->add($firewallOptions['password_parameter'], PasswordType::class, ['label' => 'label.password']) // Label should be configurable
            ->setAction($this->urlGenerator->generate($firewallOptions['check_path']))
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use($firewallOptions) {
            if ($error = $this->authenticationUtils->getLastAuthenticationError()) {
                $event->getForm()->addError(new FormError(
                    $this->translator->trans($error->getMessageKey(), $error->getMessageData(), 'security')
                ));
            }

            $event->setData(array_replace((array) $event->getData(), [
                $firewallOptions['username_parameter'] => $this->authenticationUtils->getLastUsername(),
            ]));
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            ''
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return '';
    }
}

