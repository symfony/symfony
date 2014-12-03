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

/**
 * Tests UserPasswordEncoderCommand
 *
 * @author Sarah Khalil <mkhalil.sarah@gmail.com>
 */
class UserPasswordEncoderCommandTest extends WebTestCase
{
    private $passwordEncoderCommandTester;

    public function testEncodePasswordPasswordPlainText()
    {
        $this->passwordEncoderCommandTester->execute(array(
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Symfony\Component\Security\Core\User\User',
            'salt' => 'AZERTYUIOPOfghjklytrertyuiolnbcxdfghjkytrfghjk',
        ));
        $expected = file_get_contents(__DIR__.'/app/PasswordEncode/plaintext.txt');

        $this->assertEquals($expected, $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodePasswordBcrypt()
    {
        $this->passwordEncoderCommandTester->execute(array(
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Custom\Class\Bcrypt\User',
            'salt' => 'AZERTYUIOPOfghjklytrertyuiolnbcxdfghjkytrfghjk',
        ));
        $expected = file_get_contents(__DIR__.'/app/PasswordEncode/bcrypt.txt');

        $this->assertEquals($expected, $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodePasswordPbkdf2()
    {
        $this->passwordEncoderCommandTester->execute(array(
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Custom\Class\Pbkdf2\User',
            'salt' => 'AZERTYUIOPOfghjklytrertyuiolnbcxdfghjkytrfghjk',
        ));

        $expected = file_get_contents(__DIR__.'/app/PasswordEncode/pbkdf2.txt');

        $this->assertEquals($expected, $this->passwordEncoderCommandTester->getDisplay());
    }

    public function testEncodePasswordNoConfigForGivenUserClass()
    {
        $this->setExpectedException('\RuntimeException', 'No encoder has been configured for account "Wrong/User/Class".');

        $this->passwordEncoderCommandTester->execute(array(
            'command' => 'security:encode-password',
            'password' => 'password',
            'user-class' => 'Wrong/User/Class',
            'salt' => 'AZERTYUIOPOfghjklytrertyuiolnbcxdfghjkytrfghjk',
        ));
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
