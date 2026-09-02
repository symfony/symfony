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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\PasswordHasher\EventListener\PasswordHasherListener;
use Symfony\Component\Form\Extension\PasswordHasher\PasswordHasherExtension;
use Symfony\Component\Form\Extension\PasswordHasher\Type\FormTypePasswordHasherExtension;
use Symfony\Component\Form\Extension\PasswordHasher\Type\PasswordTypePasswordHasherExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapperInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\Tests\Fixtures\RepeatedPasswordField;
use Symfony\Component\Form\Tests\Fixtures\User;
use Symfony\Component\PasswordHasher\Hasher\MessageDigestPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\ValidatorBuilder;

class PasswordTypePasswordHasherExtensionTest extends TypeTestCase
{
    protected UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        if (!interface_exists(PasswordAuthenticatedUserInterface::class)) {
            $this->markTestSkipped('PasswordAuthenticatedUserInterface not available.');
        }

        $this->passwordHasher = new UserPasswordHasher(new PasswordHasherFactory([
            User::class => new MessageDigestPasswordHasher('md5', false),
        ]));
        $this->dispatcher = new EventDispatcher();

        parent::setUp();
    }

    protected function getExtensions(?ViolationMapperInterface $violationMapper = null): array
    {
        return array_merge(parent::getExtensions(), [
            new ValidatorExtension((new ValidatorBuilder())->getValidator(), $violationMapper),
            new PasswordHasherExtension(new PasswordHasherListener($this->passwordHasher)),
        ]);
    }

    public function testPasswordHashSuccess()
    {
        $user = new User();

        $plainPassword = 'PlainPassword';
        $hashedPassword = 'ec2d1846a8e988d344750b904739e19b';

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
        $hashedPassword = 'ec2d1846a8e988d344750b904739e19b';

        $this->assertNull($user->getPassword());

        $form = $this->factory
            ->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'data_class' => User::class,
                'empty_data' => static fn () => $user,
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

    #[DataProvider('provideRepeatedPasswordField')]
    public function testRepeatedPasswordField(string $type, array $options = [])
    {
        $user = new User();

        $plainPassword = 'PlainPassword';
        $hashedPassword = 'ec2d1846a8e988d344750b904739e19b';

        $this->assertNull($user->getPassword());

        $form = $this->factory
            ->createBuilder(FormType::class, $user)
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

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testLegacyPasswordHashWithoutValidator()
    {
        $this->expectUserDeprecationMessage('Since symfony/form 8.2: The "Symfony\Component\Form\Extension\PasswordHasher\Type\FormTypePasswordHasherExtension" class is deprecated, passwords are hashed during the "form.post_validate" event instead.');
        $this->expectUserDeprecationMessage('Since symfony/form 8.2: The "Symfony\Component\Form\Extension\PasswordHasher\EventListener\PasswordHasherListener::registerPassword()" method is deprecated, use "hashPassword()" instead.');
        $this->expectUserDeprecationMessage('Since symfony/form 8.2: The "Symfony\Component\Form\Extension\PasswordHasher\EventListener\PasswordHasherListener::hashPasswords()" method is deprecated, use "hashPassword()" instead.');

        $listener = new PasswordHasherListener($this->passwordHasher);
        $factory = Forms::createFormFactoryBuilder()
            ->addTypeExtension(new FormTypePasswordHasherExtension($listener))
            ->addTypeExtension(new PasswordTypePasswordHasherExtension($listener))
            ->getFormFactory()
        ;

        $user = new User();

        $form = $factory
            ->createBuilder(FormType::class, $user)
            ->add('plainPassword', PasswordType::class, [
                'hash_property_path' => 'password',
                'mapped' => false,
            ])
            ->getForm()
        ;

        $form->submit(['plainPassword' => 'PlainPassword']);

        $this->assertTrue($form->isValid());
        $this->assertSame('ec2d1846a8e988d344750b904739e19b', $user->getPassword());
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
