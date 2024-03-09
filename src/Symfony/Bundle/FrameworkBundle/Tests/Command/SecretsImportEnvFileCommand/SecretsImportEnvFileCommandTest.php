<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command\SecretsImportEnvFileCommand;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\SecretsImportEnvFileCommand;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class SecretsImportEnvFileCommandTest extends TestCase
{
    /** @var array<string, string> $secrets */
    private array $secrets = [];

    public function testComplete()
    {
        $vaultSpy = $this->createVaultSpy();
        $command = new SecretsImportEnvFileCommand($vaultSpy);
        $input = new StringInput(__DIR__.'/Fixture/fake_env');
        $command->run($input, new NullOutput());

        $this->assertSame(['FAKE_ENV' => '1234'], $this->secrets);
    }

    private function createVaultSpy(): AbstractVault
    {
        return new class($this->secrets) extends AbstractVault{
            /**
             * @param array<string, string> $secrets
             */
            public function __construct(
                private array &$secrets
            ){}
            public function generateKeys(bool $override = false): bool
            {
                return false;
            }

            public function seal(string $name, string $value): void
            {
                $this->secrets[$name] = $value;
            }

            public function reveal(string $name): ?string
            {
                return $this->secrets[$name] ?? null;
            }

            public function remove(string $name): bool
            {
                $exists = array_key_exists($name, $this->secrets);
                unset($this->secrets[$name]);
                return $exists;
            }

            public function list(bool $reveal = false): array
            {
                return $this->secrets;
            }
        };
    }
}
