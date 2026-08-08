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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
#[AsCommand(
    name: 'key-management:decrypt',
    description: 'Decrypt a value previously encrypted by key-management:encrypt',
    help: <<<'HELP'
        The <info>%command.name%</info> command decrypts a base64-encoded envelope produced by
        <info>key-management:encrypt</info> and writes the plaintext to STDOUT byte-for-byte.

          <info>php %command.full_name% 'AQI...base64...'</info>
          <info>php %command.full_name% < envelope.b64</info>

        The key id is read from the envelope itself, so no <comment>envelope</comment> argument is needed.

        When several KMS clients are registered, pick one with <comment>--client</comment>. If
        <comment>--aad</comment> was used at encryption time, the same exact bytes must be supplied here.
        HELP,
)]
final class DecryptCommand
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
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        #[Argument(description: 'Base64-encoded envelope produced by key-management:encrypt; read from STDIN if omitted')]
        ?string $envelope = null,
        #[Option(description: 'Name of the KMS client to use (omit when only one is registered)', suggestedValues: [self::class, 'suggestClients'])]
        ?string $client = null,
        #[Option(description: 'Additional Authenticated Data; must match the value supplied at encryption time')]
        ?string $aad = null,
    ): int {
        $errorIo = $io->getErrorStyle();

        $encrypter = self::resolveEnvelopeEncrypter($this->clients, $client, $errorIo);
        if (!$encrypter instanceof EnvelopeEncrypter) {
            return $encrypter;
        }

        if (null === $envelope) {
            if (null === $stream = self::resolvePayloadStream($input)) {
                $errorIo->error('No envelope to decrypt: pass it as an argument or pipe it to STDIN.');

                return Command::INVALID;
            }

            $envelope = stream_get_contents($stream);
            if (false === $envelope) {
                $errorIo->error('Failed to read envelope from STDIN.');

                return Command::FAILURE;
            }
        }

        $bytes = base64_decode(trim($envelope), true);
        if (false === $bytes) {
            $errorIo->error('The envelope is not valid base64.');

            return Command::INVALID;
        }

        try {
            $parsed = Envelope::fromBytes($bytes);
        } catch (InvalidArgumentException) {
            $errorIo->error('The envelope is malformed.');

            return Command::INVALID;
        }

        try {
            $plaintext = $encrypter->decrypt($parsed, $aad ?? '');
        } catch (DecryptionFailedException) {
            $errorIo->error('Decryption failed.');

            return Command::FAILURE;
        } catch (LogicException) {
            $errorIo->error('The envelope refers to a data key held in a store, which this command cannot reach. Decrypt it through the application instead.');

            return Command::INVALID;
        }

        $output->write($plaintext, false, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
