<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Scheduler\Cron\CronGenerator;
use Symfony\Component\Scheduler\Cron\CronInterface;
use Symfony\Component\Scheduler\Cron\CronRegistry;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GenerateCronCommand extends Command
{
    private $generator;
    private $registry;
    protected static $defaultName = 'scheduler:generate';

    public function __construct(CronGenerator $generator, CronRegistry $registry)
    {
        $this->generator = $generator;
        $this->registry = $registry;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Generate the cron file for each scheduler')
            ->setDefinition([
                new InputArgument('schedulers', InputArgument::IS_ARRAY, 'The name of schedulers to generate files for, if empty, all the schedulers are selected'),
                new InputOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'The directory where the file will be generated', '/etc/cron.d'),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $schedulers = $input->getArgument('schedulers');

        $crons = empty($schedulers) ? $this->registry->toArray() : $this->registry->filter(function (CronInterface $cron, string $name) use ($schedulers): bool {
            return \in_array($name, $schedulers);
        });

        if (empty($crons)) {
            $io->warning('No cron file found, please be sure that at least a scheduler is defined');

            return 1;
        }

        $directory = $input->getOption('directory');

        $table = new Table($output);
        $table->setHeaders(['Name', 'Directory']);

        try {
            foreach ($crons as $key => $cron) {
                $this->generator->generate($key, $directory);
                $this->generator->write($cron->getExpression(), $key, $directory);
                $table->addRow([$key, $directory]);
            }
        } catch (IOException $exception) {
            $io->error(sprintf('An error occurred: %s', $exception->getMessage()));

            return 1;
        }

        $io->success(sprintf('Cron files have been generated for schedulers at "%s"', $directory));

        $table->render();

        return 0;
    }
}
