<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 */
class PasswordHasherListener implements EventSubscriberInterface
{
    private $passwordHasher;
    private $propertyAccessor;

    /** @var FormType[] */
    private static $passwordForms = [];

    public function __construct(UserPasswordHasherInterface $passwordHasher = null, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->passwordHasher = $passwordHasher;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public static function getSubscribedEvents()
    {
        return [
            FormEvents::POST_SUBMIT => [
                ['setPasswordForms', -2047],
                ['hashPasswords', -2048],
            ],
        ];
    }

    public function setPasswordForms(FormEvent $event)
    {
        $form = $event->getForm();
        $parentForm = $form->getParent();

        if (
            $parentForm
            && ($parentForm->getData() instanceof PasswordAuthenticatedUserInterface)
            && !($parentForm->getConfig()->getType()->getInnerType() instanceof RepeatedType)
        ) {
            $config = $form->getConfig();
            $innerType = $config->getType()->getInnerType();
            switch (true) {
                case $innerType instanceof PasswordType && $config->getOption('hash_password'):
                case $innerType instanceof RepeatedType && PasswordType::class == $config->getOption('type') && $form->get('first')->getConfig()->getOption('hash_password'):
                    self::$passwordForms[] = $form;
            }
        }
    }

    public function hashPasswords(FormEvent $event)
    {
        $form = $event->getForm();

        if ($form->isRoot() && $form->isValid()) {
            foreach (self::$passwordForms as $passwordForm) {
                $user = $passwordForm->getParent()->getData();
                $this->propertyAccessor->setValue(
                    $user,
                    $passwordForm->getPropertyPath(),
                    $this->passwordHasher->hashPassword($user, $passwordForm->getData())
                );
            }
        }
    }
}
