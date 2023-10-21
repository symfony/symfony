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
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\PasswordHasher\EventListener\PasswordHasherListener;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 */
class PasswordTypePasswordHasherExtension extends AbstractTypeExtension
{
    public function __construct(
        private PasswordHasherListener $passwordHasherListener,
    ) {
    }

    /**
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['hash_property_path']) {
            $builder->addEventListener(FormEvents::POST_SUBMIT, [$this->passwordHasherListener, 'registerPassword']);
        }
    }

    /**
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'hash_property_path' => null,
        ]);

        $resolver->setAllowedTypes('hash_property_path', ['null', 'string', PropertyPath::class]);

        $resolver->setInfo('hash_property_path', 'A valid PropertyAccess syntax where the hashed password will be set.');
    }

    public static function getExtendedTypes(): iterable
    {
        return [PasswordType::class];
    }
}
