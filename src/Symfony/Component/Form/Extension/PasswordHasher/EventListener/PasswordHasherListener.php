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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
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

    public function registerPassword(FormEvent $event)
    {
        $form = $event->getForm();
        $parentForm = $form->getParent();
        $mapped = $form->getConfig()->getMapped();

        if ($parentForm && $parentForm->getConfig()->getType()->getInnerType() instanceof RepeatedType) {
            $mapped = $parentForm->getConfig()->getMapped();
            $parentForm = $parentForm->getParent();
        }

        if ($mapped) {
            throw new InvalidConfigurationException('The "hash_property_path" option cannot be used on mapped field.');
        }

        if (!($user = $parentForm?->getData()) || !$user instanceof PasswordAuthenticatedUserInterface) {
            throw new InvalidConfigurationException(sprintf('The "hash_property_path" option only supports "%s" objects, "%s" given.', PasswordAuthenticatedUserInterface::class, get_debug_type($user)));
        }

        $this->passwords[] = [
            'user' => $user,
            'property_path' => $form->getConfig()->getOption('hash_property_path'),
            'password' => $event->getData(),
        ];
    }

    public function hashPasswords(FormEvent $event)
    {
        $form = $event->getForm();

        if (!$form->isRoot()) {
            return;
        }

        if ($form->isValid()) {
            foreach ($this->passwords as $password) {
                $this->propertyAccessor->setValue(
                    $password['user'],
                    $password['property_path'],
                    $this->passwordHasher->hashPassword($password['user'], $password['password'])
                );
            }
        }

        $this->passwords = [];
    }
}
