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
 *
 * @deprecated since Symfony 8.2, passwords are hashed during the "form.post_validate" event, which requires the validator extension
 */
class FormTypePasswordHasherExtension extends AbstractTypeExtension
{
    public function __construct(
        private PasswordHasherListener $passwordHasherListener,
    ) {
        trigger_deprecation('symfony/form', '8.2', 'The "%s" class is deprecated, passwords are hashed during the "form.post_validate" event instead.', self::class);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['hash_property_path'] ?? false) {
            $builder->addEventListener(FormEvents::POST_SUBMIT, [$this->passwordHasherListener, 'registerPassword']);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this->passwordHasherListener, 'hashPasswords']);
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
