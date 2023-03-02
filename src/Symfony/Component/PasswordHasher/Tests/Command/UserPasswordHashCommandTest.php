<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Command\UserPasswordHashCommand;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\SodiumPasswordHasher;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UserPasswordHashCommandTest extends TestCase
{
    /** @var CommandTester */
    private $passwordHasherCommandTester;
    private $colSize;

    public function testEncodePasswordEmptySalt()
    {
        $this->passwordHasherCommandTester->execute([
            'password' => 'password',
            'user-class' => 'Symfony\Component\Security\Core\User\InMemoryUser',
            '--empty-salt' => true,
        ], ['decorated' => false]);

        $this->assertStringContainsString(' Password hash   password', $this->passwordHasherCommandTester->getDisplay());
    }

    public function testEncodeNoPasswordNoInteraction()
    {
        $statusCode = $this->passwordHasherCommandTester->execute([
        ], ['interactive' => false]);

        $this->assertStringContainsString('[ERROR] The password must not be empty.', $this->passwordHasherCommandTester->getDisplay());
        $this->assertEquals(1, $statusCode);
    }

    public function testEncodePasswordBcrypt()
    {
        $this->setupBcrypt();
        $this->passwordHasherCommandTester->execute([
            'password' => 'password',
            'user-class' => 'Custom\Class\Bcrypt\User',
        ], ['interactive' => false]);

        $output = $this->passwordHasherCommandTester->getDisplay();
        $this->assertStringContainsString('Password hashing succeeded', $output);

        $hasher = new NativePasswordHasher(null, null, 17, \PASSWORD_BCRYPT);
        preg_match('# Password hash\s{1,}([\w+\/$.]+={0,2})\s+#', $output, $matches);
        $hash = $matches[1];
        $this->assertTrue($hasher->verify($hash, 'password', null));
    }

    public function testEncodePasswordArgon2i()
    {
        if (!($sodium = SodiumPasswordHasher::isSupported() && !\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) && !\defined('PASSWORD_ARGON2I')) {
            $this->markTestSkipped('Argon2i algorithm not available.');
        }
        $this->setupArgon2i();
        $this->passwordHasherCommandTester->execute([
            'password' => 'password',
            'user-class' => 'Custom\Class\Argon2i\User',
        ], ['interactive' => false]);

        $output = $this->passwordHasherCommandTester->getDisplay();
        $this->assertStringContainsString('Password hashing succeeded', $output);

        $hasher = $sodium ? new SodiumPasswordHasher() : new NativePasswordHasher(null, null, null, \PASSWORD_ARGON2I);
        preg_match('#  Password hash\s+(\$argon2i?\$[\w,=\$+\/]+={0,2})\s+#', $output, $matches);
        $hash = $matches[1];
        $this->assertTrue($hasher->verify($hash, 'password', null));
    }

    public function testEncodePasswordArgon2id()
    {
        if (!($sodium = (SodiumPasswordHasher::isSupported() && \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13'))) && !\defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id algorithm not available.');
        }
        $this->setupArgon2id();
        $this->passwordHasherCommandTester->execute([
            'password' => 'password',
            'user-class' => 'Custom\Class\Argon2id\User',
        ], ['interactive' => false]);

        $output = $this->passwordHasherCommandTester->getDisplay();
        $this->assertStringContainsString('Password hashing succeeded', $output);

        $hasher = $sodium ? new SodiumPasswordHasher() : new NativePasswordHasher(null, null, null, \PASSWORD_ARGON2ID);
        preg_match('#  Password hash\s+(\$argon2id?\$[\w,=\$+\/]+={0,2})\s+#', $output, $matches);
        $hash = $matches[1];
        $this->assertTrue($hasher->verify($hash, 'password', null));
    }

    public function testEncodePasswordNative()
    {
        $this->passwordHasherCommandTester->execute([
            'password' => 'password',
            'user-class' => 'Custom\Class\Native\User',
        ], ['interactive' => false]);

        $output = $this->passwordHasherCommandTester->getDisplay();
        $this->assertStringContainsString('Password hashing succeeded', $output);

        $hasher = new NativePasswordHasher();
        preg_match('# Password hash\s{1,}([\w+\/$.,=]+={0,2})\s+#', $output, $matches);
        $hash = $matches[1];
        $this->assertTrue($hasher->verify($hash, 'password', null));
    }

    public function testEncodePasswordSodium()
    {
        if (!SodiumPasswordHasher::isSupported()) {
            $this->markTestSkipped('Libsodium is not available.');
        }
        $this->setupSodium();
        $this->passwordHasherCommandTester->execute([
            'password' => 'password',
            'user-class' => 'Custom\Class\Sodium\User',
        ], ['interactive' => false]);

        $output = $this->passwordHasherCommandTester->getDisplay();
        $this->assertStringContainsString('Password hashing succeeded', $output);

        preg_match('#  Password hash\s+(\$?\$[\w,=\$+\/]+={0,2})\s+#', $output, $matches);
        $hash = $matches[1];
        $this->assertTrue((new SodiumPasswordHasher())->verify($hash, 'password', null));
    }

    public function testEncodePasswordPbkdf2()
    {
        $this->passwordHasherCommandTester->execute([
            'password' => 'password',
            'user-class' => 'Custom\Class\Pbkdf2\User',
        ], ['interactive' => false]);

        $output = $this->passwordHasherCommandTester->getDisplay();
        $this->assertStringContainsString('Password hashing succeeded', $output);

        $hasher = new Pbkdf2PasswordHasher('sha512', true, 1000);
        preg_match('# Password hash\s{1,}([\w+\/]+={0,2})\s+#', $output, $matches);
        $hash = $matches[1];
        preg_match('# Generated salt\s{1,}([\w+\/]+={0,2})\s+#', $output, $matches);
        $salt = $matches[1];
        $this->assertTrue($hasher->verify($hash, 'password', $salt));
    }

    public function testEncodePasswordOutput()
    {
        $this->passwordHasherCommandTester->execute(
            [
                'password' => 'p@ssw0rd',
            ], ['interactive' => false]
        );

        $this->assertStringContainsString('Password hashing succeeded', $this->passwordHasherCommandTester->getDisplay());
        $this->assertStringContainsString(' Password hash    p@ssw0rd', $this->passwordHasherCommandTester->getDisplay());
        $this->assertStringContainsString(' Generated salt ', $this->passwordHasherCommandTester->getDisplay());
    }

    public function testEncodePasswordEmptySaltOutput()
    {
        $this->passwordHasherCommandTester->execute([
            'password' => 'p@ssw0rd',
            'user-class' => 'Symfony\Component\Security\Core\User\InMemoryUser',
            '--empty-salt' => true,
        ]);

        $this->assertStringContainsString('Password hashing succeeded', $this->passwordHasherCommandTester->getDisplay());
        $this->assertStringContainsString(' Password hash   p@ssw0rd', $this->passwordHasherCommandTester->getDisplay());
        $this->assertStringNotContainsString(' Generated salt ', $this->passwordHasherCommandTester->getDisplay());
    }

    public function testEncodePasswordNativeOutput()
    {
        $this->passwordHasherCommandTester->execute([
            'password' => 'p@ssw0rd',
            'user-class' => 'Custom\Class\Native\User',
        ], ['interactive' => false]);

        $this->assertStringNotContainsString(' Generated salt ', $this->passwordHasherCommandTester->getDisplay());
    }

    public function testEncodePasswordArgon2iOutput()
    {
        if (!(SodiumPasswordHasher::isSupported() && !\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) && !\defined('PASSWORD_ARGON2I')) {
            $this->markTestSkipped('Argon2i algorithm not available.');
        }

        $this->setupArgon2i();
        $this->passwordHasherCommandTester->execute([
            'password' => 'p@ssw0rd',
            'user-class' => 'Custom\Class\Argon2i\User',
        ], ['interactive' => false]);

        $this->assertStringNotContainsString(' Generated salt ', $this->passwordHasherCommandTester->getDisplay());
    }

    public function testEncodePasswordArgon2idOutput()
    {
        if (!(SodiumPasswordHasher::isSupported() && \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) && !\defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id algorithm not available.');
        }

        $this->setupArgon2id();
        $this->passwordHasherCommandTester->execute([
            'password' => 'p@ssw0rd',
            'user-class' => 'Custom\Class\Argon2id\User',
        ], ['interactive' => false]);

        $this->assertStringNotContainsString(' Generated salt ', $this->passwordHasherCommandTester->getDisplay());
    }

    public function testEncodePasswordSodiumOutput()
    {
        if (!SodiumPasswordHasher::isSupported()) {
            $this->markTestSkipped('Libsodium is not available.');
        }

        $this->setupSodium();
        $this->passwordHasherCommandTester->execute([
            'password' => 'p@ssw0rd',
            'user-class' => 'Custom\Class\Sodium\User',
        ], ['interactive' => false]);

        $this->assertStringNotContainsString(' Generated salt ', $this->passwordHasherCommandTester->getDisplay());
    }

    public function testEncodePasswordNoConfigForGivenUserClass()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No password hasher has been configured for account "Foo\Bar\User".');

        $this->passwordHasherCommandTester->execute([
            'password' => 'password',
            'user-class' => 'Foo\Bar\User',
        ], ['interactive' => false]);
    }

    public function testEncodePasswordAsksNonProvidedUserClass()
    {
        $this->passwordHasherCommandTester->setInputs(['Custom\Class\Pbkdf2\User', "\n"]);
        $this->passwordHasherCommandTester->execute([
            'password' => 'password',
        ], ['decorated' => false]);

        $this->assertStringContainsString(<<<EOTXT
 For which user class would you like to hash a password? [Custom\Class\Native\User]:
  [0] Custom\Class\Native\User
  [1] Custom\Class\Pbkdf2\User
  [2] Custom\Class\Test\User
  [3] Symfony\Component\Security\Core\User\InMemoryUser
EOTXT
            , $this->passwordHasherCommandTester->getDisplay(true));
    }

    public function testNonInteractiveEncodePasswordUsesFirstUserClass()
    {
        $this->passwordHasherCommandTester->execute([
            'password' => 'password',
        ], ['interactive' => false]);

        $this->assertStringContainsString('Hasher used      Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher', $this->passwordHasherCommandTester->getDisplay());
    }

    public function testThrowsExceptionOnNoConfiguredHashers()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('There are no configured password hashers for the "security" extension.');

        $tester = new CommandTester(new UserPasswordHashCommand($this->getMockBuilder(PasswordHasherFactoryInterface::class)->getMock(), []));
        $tester->execute([
            'password' => 'password',
        ], ['interactive' => false]);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testCompletionSuggestions(array $input, array $expectedSuggestions)
    {
        $command = new UserPasswordHashCommand($this->createMock(PasswordHasherFactoryInterface::class), ['App\Entity\User']);
        $tester = new CommandCompletionTester($command);

        $this->assertSame($expectedSuggestions, $tester->complete($input));
    }

    public static function provideCompletionSuggestions(): iterable
    {
        yield 'user_class_empty' => [
            ['p@ssw0rd', ''],
            ['App\Entity\User'],
        ];

        yield 'user_class_given' => [
            ['p@ssw0rd', 'App'],
            ['App\Entity\User'],
        ];
    }

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        putenv('COLUMNS='.(119 + \strlen(\PHP_EOL)));

        $hasherFactory = new PasswordHasherFactory([
            InMemoryUser::class => ['algorithm' => 'plaintext'],
            'Custom\Class\Native\User' => ['algorithm' => 'native', 'cost' => 10],
            'Custom\Class\Pbkdf2\User' => ['algorithm' => 'pbkdf2', 'hash_algorithm' => 'sha512', 'iterations' => 1000, 'encode_as_base64' => true],
            'Custom\Class\Test\User' => ['algorithm' => 'test'],
        ]);

        $this->passwordHasherCommandTester = new CommandTester(new UserPasswordHashCommand(
            $hasherFactory,
            [InMemoryUser::class, 'Custom\Class\Native\User', 'Custom\Class\Pbkdf2\User', 'Custom\Class\Test\User']
        ));
    }

    protected function tearDown(): void
    {
        $this->passwordHasherCommandTester = null;
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');
    }

    private function setupArgon2i()
    {
        $hasherFactory = new PasswordHasherFactory([
            'Custom\Class\Argon2i\User' => ['algorithm' => 'argon2i'],
        ]);

        $this->passwordHasherCommandTester = new CommandTester(
            new UserPasswordHashCommand($hasherFactory, ['Custom\Class\Argon2i\User'])
        );
    }

    private function setupArgon2id()
    {
        $hasherFactory = new PasswordHasherFactory([
            'Custom\Class\Argon2id\User' => ['algorithm' => 'argon2id'],
        ]);

        $this->passwordHasherCommandTester = new CommandTester(
            new UserPasswordHashCommand($hasherFactory, ['Custom\Class\Argon2id\User'])
        );
    }

    private function setupBcrypt()
    {
        $hasherFactory = new PasswordHasherFactory([
            'Custom\Class\Bcrypt\User' => ['algorithm' => 'bcrypt'],
        ]);

        $this->passwordHasherCommandTester = new CommandTester(new UserPasswordHashCommand(
            $hasherFactory,
            [InMemoryUser::class, 'Custom\Class\Pbkdf2\User', 'Custom\Class\Test\User']
        ));
    }

    private function setupSodium()
    {
        $hasherFactory = new PasswordHasherFactory([
            'Custom\Class\Sodium\User' => ['algorithm' => 'sodium'],
        ]);

        $this->passwordHasherCommandTester = new CommandTester(
            new UserPasswordHashCommand($hasherFactory, ['Custom\Class\Sodium\User'])
        );
    }
}
