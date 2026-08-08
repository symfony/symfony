<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
#[AsCommand(
    name: 'key-management:generate-data-key',
    description: 'Generate a data key for envelope encryption (advanced)',
    help: <<<'HELP'
        The <info>%command.name%</info> command asks the configured KMS for a fresh data key
        and prints both forms as base64-encoded JSON:

          - <comment>plaintext</comment>: the raw data key (use it locally to encrypt a payload).
          - <comment>wrapped</comment>  : the KMS-encrypted form (persist alongside the
            payload; pass it back to <info>key-management:decrypt</info> or to the API to recover the
            plaintext data key).

          <info>php %command.full_name% app-key</info>
          <info>php %command.full_name% app-key --length=64 --client=aws</info>

        This is an advanced primitive. <comment>The plaintext data key reaches STDOUT,</comment>
        which means it can be captured in shell history, log redirection or
        process listings: handle accordingly. For the common case of "encrypt
        this payload, give me a self-contained blob", use <info>key-management:encrypt</info> instead.
        HELP,
)]
final class GenerateDataKeyCommand
{
    use CommandTrait;

    /**
     * @param ServiceProviderInterface<EncrypterInterface&DecrypterInterface>|null $clients
     */
    public function __construct(
        private readonly ?ServiceProviderInterface $clients = null,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        SymfonyStyle $io,
        #[Argument(name: 'key-id', description: 'KMS master key identifier (backend-specific: alias, ARN, key URL, ...)')]
        string $keyId,
        #[Option(description: 'Length of the data key in bytes')]
        int $length = 32,
        #[Option(description: 'Name of the KMS client to use (omit when only one is registered)', suggestedValues: [self::class, 'suggestClients'])]
        ?string $client = null,
        #[Option(description: 'Additional Authenticated Data, treated as opaque bytes; must be supplied verbatim when unwrapping')]
        ?string $aad = null,
    ): int {
        $errorIo = $io->getErrorStyle();

        $kms = self::resolveDataKeyGenerator($this->clients, $client, $errorIo);
        if (!$kms instanceof DataKeyGeneratorInterface) {
            return $kms;
        }

        if ($length < 16) {
            $errorIo->error(\sprintf('Data key length must be at least 16 bytes, %d given.', $length));

            return Command::INVALID;
        }

        $dataKey = $kms->generateDataKey($keyId, $length, $aad ?? '');

        $payload = [
            'key_id' => $dataKey->wrapped->keyId,
            'length' => $length,
            'plaintext' => $dataKey->use(static fn (#[\SensitiveParameter] string $p): string => base64_encode($p)),
            'wrapped' => base64_encode($dataKey->wrapped->blob),
        ];

        $output->writeln(json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
