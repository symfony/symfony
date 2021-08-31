<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\EventListener\PasswordHasherListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 */
class PasswordHasherExtension extends AbstractTypeExtension
{
    private $passwordHasher;
    private $propertyAccessor;

    public function __construct(UserPasswordHasherInterface $passwordHasher = null, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->passwordHasher = $passwordHasher;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new PasswordHasherListener($this->passwordHasher, $this->propertyAccessor));
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
