<?php

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

trait Interactive {
    /**
     * Interactively runs the command.
     *
     * The code to execute is either defined directly with the
     * setCode() method or by overriding the execute() method
     * in a sub-class.
     *
     * Asks question when missing arguments.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return int     The command exit code
     *
     * @throws \Exception
     *
     * @see setCode()
     * @see execute()
     *
     * @api
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        if (null !== $this->processTitle) {
            if (function_exists('cli_set_process_title')) {
                cli_set_process_title($this->processTitle);
            } elseif (function_exists('setproctitle')) {
                setproctitle($this->processTitle);
            } elseif (OutputInterface::VERBOSITY_VERY_VERBOSE === $output->getVerbosity()) {
                $output->writeln('<comment>Install the proctitle PECL to be able to change the process title.</comment>');
            }
        }

        // force the creation of the synopsis before the merge with the app definition
        $this->getSynopsis();

        // add the application arguments and options
        $this->mergeApplicationDefinition();

        // bind the input against the command specific arguments/options
        try {
            $input->bind($this->definition);
        } catch (\Exception $e) {
            if (!$this->ignoreValidationErrors) {
                throw $e;
            }
        }

        $this->initialize($input, $output);

        if ($input->isInteractive()) {
            $this->interact($input, $output);
        }

        try {
            $input->validate();
        } catch (\RuntimeException $ex) {
            foreach (array_keys($this->definition->getArguments()) as $argument) {
                if (! isset($input->getArguments()[$argument])) {
                    $helper = $this->getHelperSet()->get('question');

                    $question = new Question($this->definition->getArguments()[$argument]->getDescription());
                    $input->setArgument($argument, $helper->ask($input, $output, $question));
                }
            }

            $input->validate();
        }

        if ($this->code) {
            $statusCode = call_user_func($this->code, $input, $output);
        } else {
            $statusCode = $this->execute($input, $output);
        }

        return is_numeric($statusCode) ? (int) $statusCode : 0;
    }
}
