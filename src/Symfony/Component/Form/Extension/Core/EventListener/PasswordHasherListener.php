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
    private static $passwordTypes = [];

    public function __construct(UserPasswordHasherInterface $passwordHasher = null, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->passwordHasher = $passwordHasher;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public static function getSubscribedEvents()
    {
        return [
            FormEvents::POST_SUBMIT => ['hashPasswords', -2048],
        ];
    }

    public function registerPasswordType(FormEvent $event)
    {
        $form = $event->getForm();
        $parentForm = $form->getParent();
        $rootName = $form->getRoot()->getName();

        if ($parentForm && $parentForm->getConfig()->getType()->getInnerType() instanceof RepeatedType) {
            if ('first' == $form->getName()) {
                self::$passwordTypes[$rootName][] = $parentForm;
            }
        } else {
            self::$passwordTypes[$rootName][] = $form;
        }
    }

    public function hashPasswords(FormEvent $event)
    {
        $form = $event->getForm();

        if ($form->isRoot() && $form->isValid() && isset(self::$passwordTypes[$form->getName()])) {
            foreach (self::$passwordTypes[$form->getName()] as $passwordType) {
                if (($user = $passwordType->getParent()->getData()) && ($user instanceof PasswordAuthenticatedUserInterface)) {
                    $this->propertyAccessor->setValue(
                        $user,
                        $passwordType->getPropertyPath(),
                        $this->passwordHasher->hashPassword($user, $passwordType->getData())
                    );
                }
            }
        }
    }
}
