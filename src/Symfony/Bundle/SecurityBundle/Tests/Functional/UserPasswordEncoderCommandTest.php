<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\SecurityBundle\Command\UserPasswordEncoderCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Encoder\Argon2iPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\Pbkdf2PasswordEncoder;

/**
 * Tests UserPasswordEncoderCommand.
 *
 * @author Sarah Khalil <mkhalil.sarah@gmail.com>
 */
class UserPasswordEncoderCommandTest extends AbstractWebTestCase
{
    /** @var CommandTester */
    private $passwordEncoderCommandTester;

    public function testEncodePasswordEmptySalt()
    {
        $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Symfony\Component\Security\Core\User\User',
            '--empty-salt' => true,
        ], ['decorated' => false]);
        $expected = str_replace("\n", \PHP_EOL, file_get_contents(__DIR__.'/app/PasswordEncode/emptysalt.txt'));

        $this->assertEquals($expected, $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodeNoPasswordNoInteraction()
    {
        $statusCode = $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
        ], ['interactive' => false]);

        $this->assertStringContainsString('[ERROR] The password must not be empty.', $this->passwordEncoderCommandTester->getDisplay());
        $this->assertEquals(1, $statusCode);
    }

    public function testEncodePasswordBcrypt()
    {
        $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Custom\Class\Bcrypt\User',
        ], ['interactive' => false]);

        $output = $this->passwordEncoderCommandTester->getDisplay();
        $this->assertStringContainsString('Password encoding succeeded', $output);

        $encoder = new BCryptPasswordEncoder(17);
        preg_match('# Encoded password\s{1,}([\w+\/$.]+={0,2})\s+#', $output, $matches);
        $hash = $matches[1];
        $this->assertTrue($encoder->isPasswordValid($hash, 'password', null));
    }

    public function testEncodePasswordArgon2i()
    {
        if (!Argon2iPasswordEncoder::isSupported()) {
            $this->markTestSkipped('Argon2i algorithm not available.');
        }
        $this->setupArgon2i();
        $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Custom\Class\Argon2i\User',
        ], ['interactive' => false]);

        $output = $this->passwordEncoderCommandTester->getDisplay();
        $this->assertStringContainsString('Password encoding succeeded', $output);

        $encoder = new Argon2iPasswordEncoder();
        preg_match('#  Encoded password\s+(\$argon2id?\$[\w,=\$+\/]+={0,2})\s+#', $output, $matches);
        $hash = $matches[1];
        $this->assertTrue($encoder->isPasswordValid($hash, 'password', null));
    }

    public function testEncodePasswordPbkdf2()
    {
        $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Custom\Class\Pbkdf2\User',
        ], ['interactive' => false]);

        $output = $this->passwordEncoderCommandTester->getDisplay();
        $this->assertStringContainsString('Password encoding succeeded', $output);

        $encoder = new Pbkdf2PasswordEncoder('sha512', true, 1000);
        preg_match('# Encoded password\s{1,}([\w+\/]+={0,2})\s+#', $output, $matches);
        $hash = $matches[1];
        preg_match('# Generated salt\s{1,}([\w+\/]+={0,2})\s+#', $output, $matches);
        $salt = $matches[1];
        $this->assertTrue($encoder->isPasswordValid($hash, 'password', $salt));
    }

    public function testEncodePasswordOutput()
    {
        $this->passwordEncoderCommandTester->execute(
            [
                'command' => 'security:encode-password',
                'password' => 'p@ssw0rd',
            ], ['interactive' => false]
        );

        $this->assertStringContainsString('Password encoding succeeded', $this->passwordEncoderCommandTester->getDisplay());
        $this->assertStringContainsString(' Encoded password   p@ssw0rd', $this->passwordEncoderCommandTester->getDisplay());
        $this->assertStringContainsString(' Generated salt ', $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodePasswordEmptySaltOutput()
    {
        $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
            'password' => 'p@ssw0rd',
            'user-class' => 'Symfony\Component\Security\Core\User\User',
            '--empty-salt' => true,
        ]);

        $this->assertStringContainsString('Password encoding succeeded', $this->passwordEncoderCommandTester->getDisplay());
        $this->assertStringContainsString(' Encoded password   p@ssw0rd', $this->passwordEncoderCommandTester->getDisplay());
        $this->assertStringNotContainsString(' Generated salt ', $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodePasswordBcryptOutput()
    {
        $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
            'password' => 'p@ssw0rd',
            'user-class' => 'Custom\Class\Bcrypt\User',
        ], ['interactive' => false]);

        $this->assertStringNotContainsString(' Generated salt ', $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodePasswordArgon2iOutput()
    {
        if (!Argon2iPasswordEncoder::isSupported()) {
            $this->markTestSkipped('Argon2i algorithm not available.');
        }

        $this->setupArgon2i();
        $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
            'password' => 'p@ssw0rd',
            'user-class' => 'Custom\Class\Argon2i\User',
        ], ['interactive' => false]);

        $this->assertStringNotContainsString(' Generated salt ', $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodePasswordNoConfigForGivenUserClass()
    {
        $this->expectException('\RuntimeException');
        $this->expectExceptionMessage('No encoder has been configured for account "Foo\Bar\User".');

        $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Foo\Bar\User',
        ], ['interactive' => false]);
    }

    public function testEncodePasswordAsksNonProvidedUserClass()
    {
        $this->passwordEncoderCommandTester->setInputs(['Custom\Class\Pbkdf2\User', "\n"]);
        $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
            'password' => 'password',
        ], ['decorated' => false]);

        $this->assertStringContainsString(<<<EOTXT
 For which user class would you like to encode a password? [Custom\Class\Bcrypt\User]:
  [0] Custom\Class\Bcrypt\User
  [1] Custom\Class\Pbkdf2\User
  [2] Custom\Class\Test\User
  [3] Symfony\Component\Security\Core\User\User
EOTXT
        , $this->passwordEncoderCommandTester->getDisplay(true));
    }

    public function testNonInteractiveEncodePasswordUsesFirstUserClass()
    {
        $this->passwordEncoderCommandTester->execute([
            'command' => 'security:encode-password',
            'password' => 'password',
        ], ['interactive' => false]);

        $this->assertStringContainsString('Encoder used       Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder', $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testThrowsExceptionOnNoConfiguredEncoders()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('There are no configured encoders for the "security" extension.');
        $application = new ConsoleApplication();
        $application->add(new UserPasswordEncoderCommand($this->getMockBuilder(EncoderFactoryInterface::class)->getMock(), []));

        $passwordEncoderCommand = $application->find('security:encode-password');

        $tester = new CommandTester($passwordEncoderCommand);
        $tester->execute([
            'command' => 'security:encode-password',
            'password' => 'password',
        ], ['interactive' => false]);
    }

    /**
     * @group legacy
     * @expectedDeprecation Passing null as the first argument of "Symfony\Bundle\SecurityBundle\Command\UserPasswordEncoderCommand::__construct()" is deprecated since Symfony 3.3 and support for it will be removed in 4.0. If the command was registered by convention, make it a service instead.
     */
    public function testLegacy()
    {
        $application = new ConsoleApplication();
        $application->add(new UserPasswordEncoderCommand());

        $passwordEncoderCommand = $application->find('security:encode-password');
        self::bootKernel(['test_case' => 'PasswordEncode']);
        $passwordEncoderCommand->setContainer(self::$kernel->getContainer());

        $tester = new CommandTester($passwordEncoderCommand);
        $tester->execute([
            'command' => 'security:encode-password',
            'password' => 'password',
        ], ['interactive' => false]);

        $this->assertStringContainsString('Encoder used       Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder', $tester->getDisplay());
    }

    protected function setUp()
    {
        putenv('COLUMNS='.(119 + \strlen(\PHP_EOL)));
        $kernel = $this->createKernel(['test_case' => 'PasswordEncode']);
        $kernel->boot();

        $application = new Application($kernel);

        $passwordEncoderCommand = $application->get('security:encode-password');

        $this->passwordEncoderCommandTester = new CommandTester($passwordEncoderCommand);
    }

    protected function tearDown()
    {
        $this->passwordEncoderCommandTester = null;
    }

    private function setupArgon2i()
    {
        putenv('COLUMNS='.(119 + \strlen(\PHP_EOL)));
        $kernel = $this->createKernel(['test_case' => 'PasswordEncode', 'root_config' => 'argon2i.yml']);
        $kernel->boot();

        $application = new Application($kernel);

        $passwordEncoderCommand = $application->get('security:encode-password');

        $this->passwordEncoderCommandTester = new CommandTester($passwordEncoderCommand);
    }
}
