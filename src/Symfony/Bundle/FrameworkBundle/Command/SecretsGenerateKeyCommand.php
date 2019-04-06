<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SecretsGenerateKeyCommand extends Command
{
    protected static $defaultName = 'secrets:generate-key';

    protected function configure()
    {
        $this
            ->setDescription('Prints a randomly generated encryption key.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $encryptionKey = sodium_crypto_stream_keygen();

        $output->write($encryptionKey, false, OutputInterface::OUTPUT_RAW);

        sodium_memzero($encryptionKey);
    }
}
