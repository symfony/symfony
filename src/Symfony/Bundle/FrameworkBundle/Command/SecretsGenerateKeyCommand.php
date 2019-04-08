<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Bundle\FrameworkBundle\Exception\EncryptionKeyNotFoundException;
use Symfony\Bundle\FrameworkBundle\Secret\Encoder\EncoderInterface;
use Symfony\Bundle\FrameworkBundle\Secret\Storage\MutableSecretStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class SecretsGenerateKeyCommand extends Command
{
    protected static $defaultName = 'secrets:generate-key';
    private $secretsStorage;
    private $encoder;

    public function __construct(EncoderInterface $encoder, MutableSecretStorageInterface $secretsStorage)
    {
        $this->secretsStorage = $secretsStorage;
        $this->encoder = $encoder;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDefinition([
                new InputOption('rekey', 'r', InputOption::VALUE_NONE, 'Re-encrypt previous secret with the new key.'),
            ])
            ->setDescription('Generates a new encryption key.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command generates a new encryption key.

    %command.full_name%

If a previous encryption key already exists, the command must be called with
the <info>--rekey</info> option in order to override that key and re-encrypt 
previous secrets.

    %command.full_name% --rekey
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rekey = $input->getOption('rekey');

        $previousSecrets = [];
        try {
            foreach ($this->secretsStorage->listSecrets(true) as $name => $decryptedSecret) {
                $previousSecrets[$name] = $decryptedSecret;
            }
        } catch (EncryptionKeyNotFoundException $e) {
            if (!$rekey) {
                throw $e;
            }
        }

        $keys = $this->encoder->generateKeys($rekey);
        foreach ($previousSecrets as $name => $decryptedSecret) {
            $this->secretsStorage->setSecret($name, $decryptedSecret);
        }

        $io = new SymfonyStyle($input, $output);
        switch (\count($keys)) {
            case 0:
                $io->success('Keys have been generated.');
                break;
            case 1:
                $io->success(sprintf('A key has been generated in "%s".', $keys[0]));
                $io->caution('DO NOT COMMIT that file!');
                break;
            default:
                $io->success(sprintf("Keys have been generated in :\n -%s", implode("\n -", $keys)));
                $io->caution('DO NOT COMMIT those files!');
                break;
        }
    }
}
