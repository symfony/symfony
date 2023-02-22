<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\PasswordHasher\Type;

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\PasswordHasher\EventListener\PasswordHasherListener;
use Symfony\Component\Form\Extension\PasswordHasher\PasswordHasherExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\Tests\Fixtures\RepeatedPasswordField;
use Symfony\Component\Form\Tests\Fixtures\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class PasswordTypePasswordHasherExtensionTest extends TypeTestCase
{
    /**
     * @var MockObject&UserPasswordHasherInterface
     */
    protected $passwordHasher;

    protected function setUp(): void
    {
        if (!interface_exists(PasswordAuthenticatedUserInterface::class)) {
            $this->markTestSkipped('PasswordAuthenticatedUserInterface not available.');
        }

        $this->passwordHasher = $this->createMock(UserPasswordHasher::class);

        parent::setUp();
    }

    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), [
            new PasswordHasherExtension(new PasswordHasherListener($this->passwordHasher)),
        ]);
    }

    public function testPasswordHashSuccess()
    {
        $user = new User();

        $plainPassword = 'PlainPassword';
        $hashedPassword = 'HashedPassword';

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, $plainPassword)
            ->willReturn($hashedPassword)
        ;

        $this->assertNull($user->getPassword());

        $form = $this->factory
            ->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', $user)
            ->add('plainPassword', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', [
                'hash_property_path' => 'password',
                'mapped' => false,
            ])
            ->getForm()
        ;

        $form->submit(['plainPassword' => $plainPassword]);

        $this->assertTrue($form->isValid());
        $this->assertSame($user->getPassword(), $hashedPassword);
    }

    public function testPasswordHashSkippedWithEmptyPassword()
    {
        $oldHashedPassword = 'PreviousHashedPassword';

        $user = new User();
        $user->setPassword($oldHashedPassword);

        $this->passwordHasher
            ->expects($this->never())
            ->method('hashPassword')
        ;

        $this->assertEquals($user->getPassword(), $oldHashedPassword);

        $form = $this->factory
            ->createBuilder(FormType::class, $user)
            ->add('plainPassword', PasswordType::class, [
                'hash_property_path' => 'password',
                'mapped' => false,
                'required' => false,
            ])
            ->getForm()
        ;

        $form->submit(['plainPassword' => '']);

        $this->assertTrue($form->isValid());
        $this->assertSame($user->getPassword(), $oldHashedPassword);
    }

    public function testPasswordHashSuccessWithEmptyData()
    {
        $user = new User();

        $plainPassword = 'PlainPassword';
        $hashedPassword = 'HashedPassword';

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, $plainPassword)
            ->willReturn($hashedPassword)
        ;

        $this->assertNull($user->getPassword());

        $form = $this->factory
            ->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'data_class' => User::class,
                'empty_data' => function () use ($user) {
                    return $user;
                },
            ])
            ->add('plainPassword', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', [
                'hash_property_path' => 'password',
                'mapped' => false,
            ])
            ->getForm()
        ;

        $form->submit(['plainPassword' => $plainPassword]);

        $this->assertTrue($form->isValid());
        $this->assertSame($user->getPassword(), $hashedPassword);
    }

    /**
     * @dataProvider provideRepeatedPasswordField
     */
    public function testRepeatedPasswordField(string $type, array $options = [])
    {
        $user = new User();

        $plainPassword = 'PlainPassword';
        $hashedPassword = 'HashedPassword';

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, $plainPassword)
            ->willReturn($hashedPassword)
        ;

        $this->assertNull($user->getPassword());

        $form = $this->factory
            ->createBuilder(data: $user)
            ->add('plainPassword', $type, $options)
            ->getForm()
        ;

        $form->submit(['plainPassword' => ['first' => $plainPassword, 'second' => $plainPassword]]);

        $this->assertTrue($form->isValid());
        $this->assertSame($user->getPassword(), $hashedPassword);
    }

    public static function provideRepeatedPasswordField(): iterable
    {
        yield 'RepeatedType' => [
            RepeatedType::class,
            [
                'type' => PasswordType::class,
                'first_options' => [
                    'hash_property_path' => 'password',
                ],
                'mapped' => false,
            ],
        ];

        yield 'RepeatedType child' => [RepeatedPasswordField::class];
    }

    public function testPasswordHashOnInvalidForm()
    {
        $user = new User();

        $this->passwordHasher
            ->expects($this->never())
            ->method('hashPassword')
        ;

        $this->assertNull($user->getPassword());

        $form = $this->factory
            ->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', $user)
            ->add('plainPassword', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', [
                'hash_property_path' => 'password',
                'mapped' => false,
            ])
            ->add('integer', 'Symfony\Component\Form\Extension\Core\Type\IntegerType', [
                'mapped' => false,
            ])
            ->getForm()
        ;

        $form->submit([
            'plainPassword' => 'PlainPassword',
            'integer' => 'text',
        ]);

        $this->assertFalse($form->isValid());
        $this->assertNull($user->getPassword());
    }

    public function testPasswordHashOnInvalidData()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "hash_property_path" option only supports "Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface" objects, "array" given.');

        $form = $this->factory
            ->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', [])
            ->add('plainPassword', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', [
                'hash_property_path' => 'password',
                'mapped' => false,
            ])
            ->getForm()
        ;

        $form->submit(['plainPassword' => 'PlainPassword']);
    }

    public function testPasswordHashOnMappedFieldForbidden()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "hash_property_path" option cannot be used on mapped field.');

        $form = $this->factory
            ->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', new User())
            ->add('password', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', [
                'hash_property_path' => 'password',
                'mapped' => true,
            ])
            ->getForm()
        ;

        $form->submit(['password' => 'PlainPassword']);
    }
}
