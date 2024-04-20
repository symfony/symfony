<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Runner\Symfony;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ConsoleApplicationRunner implements RunnerInterface
{
    public function __construct(
        private readonly Application $application,
        private readonly ?string $defaultEnv,
        private readonly InputInterface $input,
        private readonly ?OutputInterface $output = null,
    ) {
    }

    public function run(): int
    {
        if (null === $this->defaultEnv) {
            return $this->application->run($this->input, $this->output);
        }

        $definition = $this->application->getDefinition();

        if (!$definition->hasOption('env') && !$definition->hasOption('e') && !$definition->hasShortcut('e')) {
            $definition->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', $this->defaultEnv));
        }

        if (!$definition->hasOption('no-debug')) {
            $definition->addOption(new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switches off debug mode.'));
        }

        return $this->application->run($this->input, $this->output);
    }
}
