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
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
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
        return [FormEvents::POST_SUBMIT => ['postSubmit', -2048]];
    }

    public function postSubmit(FormEvent $event)
    {
        $form = $event->getForm();

        if ($form->isRoot() && $form->isValid()) {
            $data = $event->getData();
            $this->hashPasswords($form, $data);
            $event->setData($data);
        }
    }

    private function hashPasswords(FormInterface $form, &$data)
    {
        foreach ($form->all() as $field) {
            $passwordField = $field;

            if (
                $field->getConfig()->getType()->getInnerType() instanceof RepeatedType
                && PasswordType::class == $field->getConfig()->getOption('type')
            ) {
                $passwordField = $field->get('first');
            }

            if (!$passwordField->getConfig()->getType()->getInnerType() instanceof PasswordType) {
                if ($field->count()) {
                    $subData = $this->propertyAccessor->getValue($data, $field->getPropertyPath());
                    $this->hashPasswords($field, $subData);
                    $this->propertyAccessor->setValue($data, $field->getPropertyPath(), $subData);
                }
                continue;
            }

            if (
                $passwordField->getConfig()->getOption('hash_password')
                && $form->getData() instanceof PasswordAuthenticatedUserInterface
            ) {
                $this->propertyAccessor->setValue(
                    $data,
                    $field->getPropertyPath(),
                    $this->passwordHasher->hashPassword($form->getData(), $field->getData())
                );
            }
        }
    }
}
