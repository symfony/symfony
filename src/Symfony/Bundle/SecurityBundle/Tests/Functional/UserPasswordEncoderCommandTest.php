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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\Pbkdf2PasswordEncoder;

/**
 * Tests UserPasswordEncoderCommand
 *
 * @author Sarah Khalil <mkhalil.sarah@gmail.com>
 */
class UserPasswordEncoderCommandTest extends WebTestCase
{
    private $passwordEncoderCommandTester;

    public function testEncodePasswordEmptySalt()
    {
        $this->passwordEncoderCommandTester->execute(array(
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Symfony\Component\Security\Core\User\User',
            '--empty-salt' => true,
        ));
        $expected = file_get_contents(__DIR__.'/app/PasswordEncode/emptysalt.txt');

        $this->assertEquals($expected, $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodeNoPasswordNoInteraction()
    {
        $this->setExpectedException('\Exception', 'The password must not be empty.');

        $this->passwordEncoderCommandTester->execute(array(
            'command' => 'security:encode-password',
        ), array('interactive' => false));
    }

    public function testEncodePasswordBcrypt()
    {
        $this->passwordEncoderCommandTester->execute(array(
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Custom\Class\Bcrypt\User',
        ), array('interactive' => false));

        $output = $this->passwordEncoderCommandTester->getDisplay();
        $this->assertContains('Password encoding succeeded', $output);

        $encoder = new BCryptPasswordEncoder(17);
        preg_match('#\| Encoded password \| ([a-zA-Z0-9+\/$.]+={0,2})\s+\|#', $output, $matches);
        $hash = $matches[1];
        $this->assertTrue($encoder->isPasswordValid($hash, 'password', null));
    }

    public function testEncodePasswordPbkdf2()
    {
        $this->passwordEncoderCommandTester->execute(array(
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Custom\Class\Pbkdf2\User',
        ), array('interactive' => false));

        $output = $this->passwordEncoderCommandTester->getDisplay();
        $this->assertContains('Password encoding succeeded', $output);

        $encoder = new Pbkdf2PasswordEncoder('sha512', true, 1000);
        preg_match('#\| Encoded password \| ([a-zA-Z0-9+\/$.]+={0,2})\s+\|#', $output, $matches);
        $hash = $matches[1];
        preg_match('#\| Generated salt   \| ([a-zA-Z0-9+\/]+={0,2})\s+\|#', $output, $matches);
        $salt = $matches[1];
        $this->assertTrue($encoder->isPasswordValid($hash, 'password', $salt));
    }

    public function testEncodePasswordOutput()
    {
        $this->passwordEncoderCommandTester->execute(
            array(
                'command' => 'security:encode-password',
                'password' => 'p@ssw0rd',
            ), array('interactive' => false)
        );

        $this->assertContains('Password encoding succeeded', $this->passwordEncoderCommandTester->getDisplay());
        $this->assertContains('| Encoded password | p@ssw0rd', $this->passwordEncoderCommandTester->getDisplay());
        $this->assertContains('| Generated salt   |', $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodePasswordEmptySaltOutput()
    {
        $this->passwordEncoderCommandTester->execute(
            array(
                'command' => 'security:encode-password',
                'password' => 'p@ssw0rd',
                '--empty-salt' => true,
            )
        );

        $this->assertContains('Password encoding succeeded', $this->passwordEncoderCommandTester->getDisplay());
        $this->assertContains('| Encoded password | p@ssw0rd', $this->passwordEncoderCommandTester->getDisplay());
        $this->assertNotContains('| Generated salt   |', $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodePasswordNoConfigForGivenUserClass()
    {
        $this->setExpectedException('\RuntimeException', 'No encoder has been configured for account "Foo\Bar\User".');

        $this->passwordEncoderCommandTester->execute(array(
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Foo\Bar\User',
        ), array('interactive' => false));
    }

    protected function setUp()
    {
        $kernel = $this->createKernel(array('test_case' => 'PasswordEncode'));
        $kernel->boot();

        $application = new Application($kernel);

        $application->add(new UserPasswordEncoderCommand());
        $passwordEncoderCommand = $application->find('security:encode-password');

        $this->passwordEncoderCommandTester = new CommandTester($passwordEncoderCommand);
    }

    protected function tearDown()
    {
        $this->passwordEncoderCommandTester = null;
    }
}
