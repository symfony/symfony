<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Bundle\FrameworkBundle\Secret\SecretStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SecretsListCommand extends Command
{
    protected static $defaultName = 'secrets:list';

    /**
     * @var SecretStorageInterface
     */
    private $secretStorage;

    public function __construct(SecretStorageInterface $secretStorage)
    {
        $this->secretStorage = $secretStorage;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Lists all secrets.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(['key', 'plaintext secret']);

        foreach ($this->secretStorage->listSecrets() as $key => $secret) {
            $table->addRow([$key, $secret]);
        }

        $table->render();
    }
}
