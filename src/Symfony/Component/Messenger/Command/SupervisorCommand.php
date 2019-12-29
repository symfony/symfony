<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Command;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @author zorn-v (https://github.com/zorn-v)
 */
class SupervisorCommand extends Command
{
    protected static $defaultName = 'messenger:supervisor';

    private $lockFactory;
    private $config;
    private $logger;

    public function __construct(LockFactory $lockFactory, ContainerInterface $parameterBag, LoggerInterface $logger = null)
    {
        $this->lockFactory = $lockFactory;
        $this->config = $parameterBag->get('messenger.supervisor');
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Run and watch messenger:consume commands with parameters from config')
            ->setDefinition([
                new InputOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds to sleep after messenger:consume is started', 1),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (!\extension_loaded('pcntl')) {
            $io->error('pcntl extension must be installed and enabled to run this command');

            return 1;
        }

        if (empty($this->config)) {
            $io->warning('No consumers is defined in config. Exiting.');

            return 1;
        }

        $running = true;
        $consumers = [];
        $php = (new PhpExecutableFinder())->find();
        $appHash = substr(sha1(__DIR__), 0, 8);

        foreach ($this->config as $name => $params) {
            $cmd = array_merge([$php, $_SERVER['PHP_SELF'], 'messenger:consume'], $params['receivers']);
            unset($params['receivers']);
            foreach ($params as $k => $v) {
                $cmd[] = sprintf('--%s=%s', $k, $v);
            }
            $consumerName = sprintf('supervisor-%s-%s', $name, $appHash);
            $cmd[] = sprintf('--name=%s', $consumerName);
            $lockName = sprintf('%s-%s', ConsumeMessagesCommand::LOCK_PREFIX, $consumerName);
            $consumers[$name]['lock'] = $this->lockFactory->createLock($lockName);
            $consumers[$name]['process'] = new Process($cmd);
        }

        pcntl_signal(SIGTERM, function () use (&$running, $consumers) {
            $running = false;
            foreach ($consumers as $consumer) {
                $consumer['process']->signal(SIGTERM);
            }
        });

        $sleep = $input->getOption('sleep');

        $this->logger->info('Messenger supervisor started');

        while ($running) {
            foreach ($consumers as $name => $c) {
                $lock = $c['lock'];
                if ($lock->acquire()) {
                    $process = $c['process'];
                    $process->stop(0);
                    $lock->release();
                    $this->logger->warning(sprintf('Starting "%s" messenger consumer: %s', $name, $process->getCommandLine()));
                    $process->start();
                    sleep($sleep);
                }
            }
            pcntl_signal_dispatch();
            sleep(1);
        }
        foreach ($consumers as $c) {
            $c['process']->wait();
        }

        return 0;
    }
}
