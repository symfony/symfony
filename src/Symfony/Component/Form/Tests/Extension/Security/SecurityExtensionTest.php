<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Security\SecurityExtension;
use Symfony\Component\Form\Extension\Security\Type\SecurityPasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\User\User;

class SecurityExtensionTest extends TestCase
{
    public function testSecurityPasswordTypeWorks()
    {
        $user = new User('email@example.com', 'previous_password');

        $this->assertSame('previous_password', $user->getPassword());

        $form = $this->getFormFactory()
            ->createBuilder(FormType::class, $user)
            ->add('password', SecurityPasswordType::class, [
                'security_user' => $user,
            ])
            ->getForm()
        ;

        $form->submit(['password' => 'new_password']);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());
        $this->assertSame('encoded_password', $user->getPassword());
    }

    public function testSecurityPasswordTypeDetectsUserObject()
    {
        $user = new User('email@example.com', 'previous_password');

        $this->assertSame('previous_password', $user->getPassword());

        $form = $this->getFormFactory()->create(PasswordResetType::class, $user);
        $form->submit(['password' => 'new_password']);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());
        $this->assertSame('encoded_password', $user->getPassword());
    }

    public function testSecurityPasswordTypeDoesNotUpdatePasswordWhenEmpty()
    {
        $user = new User('email@example.com', 'previous_password');

        $this->assertSame('previous_password', $user->getPassword());

        $form = $this->getFormFactory()->create(PasswordResetType::class, $user);
        $form->submit([]);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());
        $this->assertSame('previous_password', $user->getPassword());
    }

    private function getFormFactory()
    {
        $userPasswordEncoder = $this->getMockBuilder('Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface')->getMock();

        $userPasswordEncoder
            ->method('encodePassword')
            ->willReturn('encoded_password');

        $formFactoryBuilder = new FormFactoryBuilder();
        $formFactoryBuilder->addExtension(new SecurityExtension($userPasswordEncoder));

        return $formFactoryBuilder->getFormFactory();
    }
}

class PasswordResetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('password', SecurityPasswordType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', User::class);
    }
}
