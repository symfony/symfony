<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RemoveSecretsLoaderWithoutVaultPass;
use Symfony\Bundle\FrameworkBundle\Secrets\DotenvVault;
use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\StaticEnvVarLoader;
use Symfony\Component\Filesystem\Filesystem;

class RemoveSecretsLoaderWithoutVaultPassTest extends TestCase
{
    private const DEFAULT_VAULT_DIRECTORY = '%kernel.project_dir%/config/secrets/%kernel.runtime_environment%';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/sf_secrets_loader_pass_100%';
        (new Filesystem())->remove($this->projectDir);
        mkdir($this->projectDir.'/config', 0o777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testLoaderIsRemovedWhenTheDirectoryOfAllVaultsIsMissing()
    {
        $container = $this->createContainer(self::DEFAULT_VAULT_DIRECTORY);

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertFalse($container->hasDefinition('secrets.env_var_loader'));
        $this->assertTrue($container->hasDefinition('secrets.vault'));
        $this->assertContainsEquals(new FileExistenceResource($this->projectDir.'/config/secrets'), $container->getResources());
    }

    public function testLoaderIsKeptWhenTheDirectoryOfAllVaultsExists()
    {
        mkdir($this->projectDir.'/config/secrets');
        $container = $this->createContainer(self::DEFAULT_VAULT_DIRECTORY);

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testLoaderIsKeptWhenAnEnvVarStartsTheVaultDirectory()
    {
        $container = $this->createContainer('%env(VAULT_DIR)%/secrets');

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testLoaderIsRemovedWhenAVaultDirectoryWithoutEnvVarsIsMissing()
    {
        mkdir($this->projectDir.'/config/secrets');
        $container = $this->createContainer('%kernel.project_dir%/config/secrets/prod');

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertFalse($container->hasDefinition('secrets.env_var_loader'));
        $this->assertContainsEquals(new FileExistenceResource($this->projectDir.'/config/secrets/prod'), $container->getResources());
    }

    public function testLoaderIsKeptWhenAVaultDirectoryWithoutEnvVarsExists()
    {
        mkdir($this->projectDir.'/config/secrets/prod', 0o777, true);
        $container = $this->createContainer('%kernel.project_dir%/config/secrets/prod');

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testLoaderIsKeptWhenTheVaultDirectoryIsOutsideTheProject()
    {
        $container = $this->createContainer(sys_get_temp_dir().'/sf_secrets_loader_pass_mount/%kernel.runtime_environment%');

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testLoaderIsKeptWhenTheVaultDirectoryIsASiblingOfTheProject()
    {
        $container = $this->createContainer('%kernel.project_dir%-vault/%kernel.runtime_environment%');

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testLoaderIsKeptWhenTheVaultDirectoryLeavesTheProject()
    {
        $container = $this->createContainer('%kernel.project_dir%/../sf_secrets_loader_pass_vault/%kernel.runtime_environment%');

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testLoaderIsKeptWhenTheVaultDirectoryIsRelative()
    {
        $container = $this->createContainer('sf_secrets_loader_pass/%kernel.runtime_environment%');

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testLoaderIsKeptWithoutProjectDirectory()
    {
        $container = $this->createContainer(str_replace('%', '%%', $this->projectDir).'/config/secrets/%kernel.runtime_environment%');
        $container->getParameterBag()->remove('kernel.project_dir');

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testEscapedPercentsInTheVaultDirectoryAreUnescaped()
    {
        mkdir($this->projectDir.'/config/100%/secrets', 0o777, true);
        $container = $this->createContainer('%kernel.project_dir%/config/100%%/secrets/%kernel.runtime_environment%');

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testLoaderIsKeptForAnotherVault()
    {
        $container = $this->createContainer(self::DEFAULT_VAULT_DIRECTORY);
        $container->getDefinition('secrets.vault')->setClass(DotenvVault::class);

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testLoaderIsKeptWhenTheVaultDirectoryIsANamedArgument()
    {
        $container = $this->createContainer(self::DEFAULT_VAULT_DIRECTORY);
        $container->getDefinition('secrets.vault')->setArguments(['$secretsDir' => $this->projectDir.'/vault']);

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    public function testLoaderIsKeptWhenTheVaultIsDecorated()
    {
        $container = $this->createContainer(self::DEFAULT_VAULT_DIRECTORY);
        $container->register('app.vault', DotenvVault::class)->setDecoratedService('secrets.vault');

        new RemoveSecretsLoaderWithoutVaultPass()->process($container);

        $this->assertTrue($container->hasDefinition('secrets.env_var_loader'));
    }

    private function createContainer(string $vaultDirectory): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'kernel.project_dir' => str_replace('%', '%%', $this->projectDir),
            'kernel.runtime_environment' => '%env(default:kernel.environment:APP_RUNTIME_ENV)%',
        ]));
        $container->register('secrets.vault', SodiumVault::class)
            ->setArguments([$vaultDirectory, null, null]);
        $container->register('secrets.env_var_loader', StaticEnvVarLoader::class)
            ->setArguments([new Reference('secrets.vault')])
            ->addTag('container.env_var_loader');

        return $container;
    }
}
