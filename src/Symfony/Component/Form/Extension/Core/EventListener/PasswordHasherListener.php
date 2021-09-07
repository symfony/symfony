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

    public function __construct(UserPasswordHasherInterface $passwordHasher = null, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->passwordHasher = $passwordHasher;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public static function getSubscribedEvents()
    {
        return [
            FormEvents::SUBMIT => ['hashPassword', -256],
        ];
    }

    public function hashPassword(FormEvent $event)
    {
        $form = $event->getForm();
        $parentForm = $form->getParent();

        if ($parentForm && $parentForm->getConfig()->getType()->getInnerType() instanceof RepeatedType) {
            $parentForm = $parentForm->getParent();
        }

        if ($parentForm && ($user = $parentForm->getData()) && ($user instanceof PasswordAuthenticatedUserInterface)) {
            $this->propertyAccessor->setValue(
                $user,
                $form->getConfig()->getOption('hash_mapping'),
                $this->passwordHasher->hashPassword($user, $event->getData())
            );
        }
    }
}
