<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\PasswordHasher\EventListener;

use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 * @author Gábor Egyed <gabor.egyed@gmail.com>
 */
class PasswordHasherListener
{
    private array $passwords = [];

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
        $this->propertyAccessor ??= PropertyAccess::createPropertyAccessor();
    }

    public function registerPassword(FormEvent $event): void
    {
        if (null === $event->getData() || '' === $event->getData()) {
            return;
        }

        $this->assertNotMapped($event->getForm());

        $this->passwords[] = [
            'form' => $event->getForm(),
            'property_path' => $event->getForm()->getConfig()->getOption('hash_property_path'),
            'password' => $event->getData(),
        ];
    }

    public function hashPasswords(FormEvent $event): void
    {
        $form = $event->getForm();

        if (!$form->isRoot()) {
            return;
        }

        if ($form->isValid()) {
            foreach ($this->passwords as $password) {
                $user = $this->getUser($password['form']);

                $this->propertyAccessor->setValue(
                    $user,
                    $password['property_path'],
                    $this->passwordHasher->hashPassword($user, $password['password'])
                );
            }
        }

        $this->passwords = [];
    }

    private function getTargetForm(FormInterface $form): FormInterface
    {
        if (!$parentForm = $form->getParent()) {
            return $form;
        }

        $parentType = $parentForm->getConfig()->getType();

        do {
            if ($parentType->getInnerType() instanceof RepeatedType) {
                return $parentForm;
            }
        } while ($parentType = $parentType->getParent());

        return $form;
    }

    private function getUser(FormInterface $form): PasswordAuthenticatedUserInterface
    {
        $parent = $this->getTargetForm($form)->getParent();

        if (!($user = $parent?->getData()) || !$user instanceof PasswordAuthenticatedUserInterface) {
            throw new InvalidConfigurationException(sprintf('The "hash_property_path" option only supports "%s" objects, "%s" given.', PasswordAuthenticatedUserInterface::class, get_debug_type($user)));
        }

        return $user;
    }

    private function assertNotMapped(FormInterface $form): void
    {
        if ($this->getTargetForm($form)->getConfig()->getMapped()) {
            throw new InvalidConfigurationException('The "hash_property_path" option cannot be used on mapped field.');
        }
    }
}
