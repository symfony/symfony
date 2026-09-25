<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\Filesystem\Filesystem;

#[Group('functional')]
#[RequiresPhpExtension('sodium')]
class SecretsTest extends AbstractWebTestCase
{
    private const VAULTS_DIR = __DIR__.'/app/Secrets/secrets';

    protected function setUp(): void
    {
        (new Filesystem())->remove(self::VAULTS_DIR);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['SECRETS_TEST_DECRYPTION_SECRET']);
        (new Filesystem())->remove(self::VAULTS_DIR);
        parent::tearDown();
    }

    public function testNoLoaderIsRegisteredWithoutVault()
    {
        $_SERVER['SECRETS_TEST_DECRYPTION_SECRET'] = base64_encode('key');
        static::bootKernel(['test_case' => 'Secrets', 'environment' => 'no_vault']);
        $container = static::getContainer();

        $this->assertFalse($container->has('secrets.env_var_loader'));
        $this->assertNull($container->getParameter('secrets_test.foo'));

        $this->expectException(EnvNotFoundException::class);
        $this->expectExceptionMessage('Environment variable not found: "SECRETS_TEST_APP_SECRET".');

        $container->getParameter('kernel.secret');
    }

    public function testVaultLoadsSecretsAndDerivesTheKernelSecret()
    {
        $vaultDir = self::VAULTS_DIR.'/vault';
        $vault = new SodiumVault($vaultDir);
        $vault->generateKeys();
        $vault->seal('SECRETS_TEST_FOO', 'bar');
        $decryptionKey = include $vaultDir.'/vault.decrypt.private.php';
        $_SERVER['SECRETS_TEST_DECRYPTION_SECRET'] = base64_encode($decryptionKey);

        static::bootKernel(['test_case' => 'Secrets', 'environment' => 'vault']);
        $container = static::getContainer();

        $this->assertTrue($container->has('secrets.env_var_loader'));
        $this->assertSame('bar', $container->getParameter('secrets_test.foo'));
        $this->assertSame(base64_encode(hash('sha256', $decryptionKey, true)), $container->getParameter('kernel.secret'));
    }

    public function testCreatingTheDirectoryOfTheVaultsRebuildsTheContainer()
    {
        $options = ['test_case' => 'Secrets', 'environment' => 'new_dir', 'debug' => true];
        static::bootKernel($options);
        $this->assertFalse(static::getContainer()->has('secrets.env_var_loader'));

        mkdir(self::VAULTS_DIR);
        static::bootKernel($options);

        $this->assertTrue(static::getContainer()->has('secrets.env_var_loader'));
    }

    public function testSecretsSetCreatesTheFirstVault()
    {
        $options = ['test_case' => 'Secrets', 'environment' => 'new_vault', 'debug' => true];
        $kernel = static::createKernel($options);
        $application = new Application($kernel);

        $tester = new CommandTester($application->find('secrets:list'));
        $this->assertSame(0, $tester->execute([]));
        $this->assertStringNotContainsString('SECRETS_TEST_FOO', $tester->getDisplay());

        file_put_contents($file = $kernel->getCacheDir().'/secret_value', 'bar');
        $tester = new CommandTester($application->find('secrets:set'));
        $this->assertSame(0, $tester->execute(['name' => 'SECRETS_TEST_FOO', 'file' => $file]));

        static::bootKernel($options);
        $this->assertSame('bar', static::getContainer()->getParameter('secrets_test.foo'));

        $tester = new CommandTester(new Application(static::$kernel)->find('secrets:list'));
        $this->assertSame(0, $tester->execute(['--reveal' => true]));
        $this->assertMatchesRegularExpression('/SECRETS_TEST_FOO +"bar"/', $tester->getDisplay());
    }
}
