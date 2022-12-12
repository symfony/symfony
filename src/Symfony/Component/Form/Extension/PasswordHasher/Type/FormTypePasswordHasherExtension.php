<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\PasswordHasher\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\PasswordHasher\EventListener\PasswordHasherListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;

/**
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 */
class FormTypePasswordHasherExtension extends AbstractTypeExtension
{
    public function __construct(
        private PasswordHasherListener $passwordHasherListener,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this->passwordHasherListener, 'hashPasswords']);
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
