<?php

namespace Symfony\Component\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * A Shell wraps an Application to add shell capabilities to it.
 *
 * This class only works with a PHP compiled with readline support
 * (either --with-readline or --with-libedit)
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Shell
{
    protected $application;
    protected $history;
    protected $output;

    /**
     * Constructor.
     *
     * If there is no readline support for the current PHP executable
     * a \RuntimeException exception is thrown.
     *
     * @param Application $application An application instance
     *
     * @throws \RuntimeException When Readline extension is not enabled
     */
    public function __construct(Application $application)
    {
        if (!function_exists('readline')) {
            throw new \RuntimeException('Unable to start the shell as the Readline extension is not enabled.');
        }

        $this->application = $application;
        $this->history = getenv('HOME').'/.history_'.$application->getName();
        $this->output = new ConsoleOutput();
    }

    /**
     * Runs the shell.
     */
    public function run()
    {
        $this->application->setAutoExit(false);
        $this->application->setCatchExceptions(true);

        readline_read_history($this->history);
        readline_completion_function(array($this, 'autocompleter'));

        $this->output->writeln($this->getHeader());
        while (true) {
            $command = readline($this->application->getName().' > ');

            if (false === $command) {
                $this->output->writeln("\n");

                break;
            }

            readline_add_history($command);
            readline_write_history($this->history);

            if (0 !== $ret = $this->application->run(new StringInput($command), $this->output)) {
                $this->output->writeln(sprintf('<error>The command terminated with an error status (%s)</error>', $ret));
            }
        }
    }

    /**
     * Tries to return autocompletion for the current entered text.
     *
     * @param string  $text     The last segment of the entered text
     * @param integer $position The current position
     */
    protected function autocompleter($text, $position)
    {
        $info = readline_info();
        $text = substr($info['line_buffer'], 0, $info['end']);

        if ($info['point'] !== $info['end']) {
            return true;
        }

        // task name?
        if (false === strpos($text, ' ') || !$text) {
            return array_keys($this->application->all());
        }

        // options and arguments?
        try {
            $command = $this->application->findCommand(substr($text, 0, strpos($text, ' ')));
        } catch (\Exception $e) {
            return true;
        }

        $list = array('--help');
        foreach ($command->getDefinition()->getOptions() as $option) {
            $list[] = '--'.$option->getName();
        }

        return $list;
    }

    /**
     * Returns the shell header.
     *
     * @return string The header string
     */
    protected function getHeader()
    {
        return <<<EOF

Welcome to the <info>{$this->application->getName()}</info> shell (<comment>{$this->application->getVersion()}</comment>).

At the prompt, type <comment>help</comment> for some help,
or <comment>list</comment> to get a list available commands.

To exit the shell, type <comment>^D</comment>.

EOF;
    }
}
