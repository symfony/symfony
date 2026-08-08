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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @internal
 */
trait CommandTrait
{
    /**
     * @return list<string>
     */
    public function suggestClients(): array
    {
        return null !== $this->clients ? array_keys($this->clients->getProvidedServices()) : [];
    }

    /**
     * Resolves the stream carrying the payload when it is not given as an argument: the stream the
     * input exposes when it has one (this is what the console testers feed), STDIN otherwise.
     *
     * Returns null when STDIN is an interactive terminal, where reading would silently block until
     * the user happens to type an end-of-file.
     *
     * @return resource|null
     */
    private static function resolvePayloadStream(InputInterface $input)
    {
        if ($input instanceof StreamableInputInterface && \is_resource($stream = $input->getStream())) {
            return $stream;
        }

        return stream_isatty(\STDIN) ? null : \STDIN;
    }

    /**
     * Diagnostics go to `$errorIo` so that they never end up in the payload stream, where a
     * downstream command reading STDIN would happily encrypt the error block.
     *
     * @param ServiceProviderInterface<EncrypterInterface&DecrypterInterface>|null $clients
     *
     * @return (EncrypterInterface&DecrypterInterface)|int the resolved client, or a Command::* exit code on failure
     */
    private static function resolveKms(?ServiceProviderInterface $clients, ?string $requested, SymfonyStyle $errorIo): (EncrypterInterface&DecrypterInterface)|int
    {
        if (null === $clients) {
            $errorIo->error('The KeyManagement component is not installed. Try running "composer require symfony/key-management".');

            return Command::FAILURE;
        }

        $names = array_keys($clients->getProvidedServices());
        if (!$names) {
            $errorIo->error('No KMS service is registered. Define a service implementing "Symfony\\Component\\KeyManagement\\EncrypterInterface" or "Symfony\\Component\\KeyManagement\\DecrypterInterface".');

            return Command::FAILURE;
        }

        if (null === $requested) {
            if (1 === \count($names)) {
                return $clients->get($names[0]);
            }
            if (\in_array('default', $names, true)) {
                return $clients->get('default');
            }
            $errorIo->error(\sprintf('Several KMS clients are registered (%s), pick one with --client.', implode(', ', $names)));

            return Command::INVALID;
        }

        if (!$clients->has($requested)) {
            $errorIo->error(\sprintf('Unknown KMS client "%s". Available: %s.', $requested, implode(', ', $names)));

            return Command::INVALID;
        }

        return $clients->get($requested);
    }

    /**
     * @param ServiceProviderInterface<EncrypterInterface&DecrypterInterface>|null $clients
     *
     * @return EnvelopeEncrypter|int the resolved encrypter, or a Command::* exit code on failure
     */
    private static function resolveEnvelopeEncrypter(?ServiceProviderInterface $clients, ?string $requested, SymfonyStyle $errorIo): EnvelopeEncrypter|int
    {
        $kms = self::resolveDataKeyGenerator($clients, $requested, $errorIo);

        return $kms instanceof DataKeyGeneratorInterface ? new EnvelopeEncrypter($kms) : $kms;
    }

    /**
     * @param ServiceProviderInterface<EncrypterInterface&DecrypterInterface>|null $clients
     *
     * @return DataKeyGeneratorInterface|int the resolved client, or a Command::* exit code on failure
     */
    private static function resolveDataKeyGenerator(?ServiceProviderInterface $clients, ?string $requested, SymfonyStyle $errorIo): DataKeyGeneratorInterface|int
    {
        $kms = self::resolveKms($clients, $requested, $errorIo);
        if (\is_int($kms)) {
            return $kms;
        }

        if (!$kms instanceof DataKeyGeneratorInterface) {
            $errorIo->error(\sprintf('The selected KMS client (%s) does not support data-key generation.', $kms::class));

            return Command::FAILURE;
        }

        return $kms;
    }
}
