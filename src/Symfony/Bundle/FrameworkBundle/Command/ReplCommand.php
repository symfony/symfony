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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Pierre du Plessus <pdples@gmail.com>
 */
final class ReplCommand extends Command
{
    protected static $defaultName = 'repl';

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputStream = $input instanceof StreamableInputInterface ? ($input->getStream() ?: STDIN) : STDIN;
        $application = $this->getApplication();
        $io = new SymfonyStyle($input, $output);

        $hasReadline = function_exists('readline');

        if ($hasReadline) {
            readline_completion_function(function () use ($application) {
                return array_keys($application->all());
            });
        }

        do {
            if ($hasReadline) {
                $text = readline('> ');
            } else {
                $output->write('> ');
                $text = fgets($inputStream, 4096);
            }

            if (false === $text) {
                throw new RuntimeException('Aborted');
            }

            $text = trim($text);

            if ('exit' === strtolower($text)) {
                break;
            }

            $parts = explode(' ', $text);

            try {
                if ($command = $this->isInternalCommand($parts[0])) {
                    $command($output, implode(' ', array_splice($parts, 1)));
                } elseif ($application->has($parts[0])) {
                    $this->runCommand($output, $application, $parts);
                } else {
                    $io->error(sprintf('Command "%s" is not recognized', $parts[0]));
                }
            } catch (\Throwable $e) {
                $application->renderException($e, $output);
            }
        } while (true);
    }

    protected function runCommand(OutputInterface $output, Application $application, array $parts): void
    {
        try {
            $command = $application->find($parts[0]);
            $input = new StringInput(implode(' ', $parts));
            $input->setInteractive(false);

            $command->run($input, $output);
        } catch (\Throwable $e) {
            $application->renderException($e, $output);
        }
    }

    private function isInternalCommand($text): ?callable
    {
        switch (strtolower($text)) {
            case 'describe':
                return function (OutputInterface $output, $text) {
                    if (!$text) {
                        throw new InvalidArgumentException('You must pass a command name to get help information');
                    }

                    $command = $this->getApplication()->get($text);

                    $helper = new DescriptorHelper();
                    $helper->describe($output, $command);
                };
            case 'env':
                return function (OutputInterface $output, $text) {
                    if ($text) {
                        $output->writeln(sprintf('%s=%s', $text, getenv($text)));
                    } else {
                        $table = new Table($output);

                        foreach (getenv() as $key => $value) {
                            $table->addRow(array($key, $value));
                        }

                        $table->render();
                    }
                };
        }

        return null;
    }
}
