<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Command\TraceableCommand;
use Symfony\Component\Console\Debug\CliRequest;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Application extends BaseApplication
{
    private bool $commandsRegistered = false;
    private array $registrationErrors = [];

    public function __construct(
        private KernelInterface $kernel,
    ) {
        parent::__construct('Symfony', Kernel::VERSION);

        $inputDefinition = $this->getDefinition();
        $inputDefinition->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', $kernel->getEnvironment()));
        $inputDefinition->addOption(new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switch off debug mode.'));
        $inputDefinition->addOption(new InputOption('--profile', null, InputOption::VALUE_NONE, 'Enables profiling (requires debug).'));
    }

    /**
     * Gets the Kernel associated with this Console.
     */
    public function getKernel(): KernelInterface
    {
        return $this->kernel;
    }

    public function reset(): void
    {
        if ($this->kernel->getContainer()->has('services_resetter')) {
            $this->kernel->getContainer()->get('services_resetter')->reset();
        }
    }

    /**
     * Runs the current application.
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->registerCommands();

        if ($this->registrationErrors) {
            $this->renderRegistrationErrors($input, $output);
        }

        $this->setDispatcher($this->kernel->getContainer()->get('event_dispatcher'));

        return parent::doRun($input, $output);
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $requestStack = null;
        $renderRegistrationErrors = true;

        if (!$command instanceof ListCommand) {
            if ($this->registrationErrors) {
                $this->renderRegistrationErrors($input, $output);
                $this->registrationErrors = [];
                $renderRegistrationErrors = false;
            }
        }

        if ($input->hasParameterOption('--profile')) {
            $container = $this->kernel->getContainer();

            if (!$this->kernel->isDebug()) {
                if ($output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }

                (new SymfonyStyle($input, $output))->warning('Debug mode should be enabled when the "--profile" option is used.');
            } elseif (!$container->has('debug.stopwatch')) {
                if ($output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }

                (new SymfonyStyle($input, $output))->warning('The "--profile" option needs the Stopwatch component. Try running "composer require symfony/stopwatch".');
            } elseif (!$container->has('.virtual_request_stack')) {
                if ($output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }

                (new SymfonyStyle($input, $output))->warning('The "--profile" option needs the profiler integration. Try enabling the "framework.profiler" option.');
            } else {
                $command = new TraceableCommand($command, $container->get('debug.stopwatch'));

                $requestStack = $container->get('.virtual_request_stack');
                $requestStack->push(new CliRequest($command));
            }
        }

        try {
            $returnCode = parent::doRunCommand($command, $input, $output);
        } finally {
            $requestStack?->pop();
        }

        if ($renderRegistrationErrors && $this->registrationErrors) {
            $this->renderRegistrationErrors($input, $output);
            $this->registrationErrors = [];
        }

        return $returnCode;
    }

    public function find(string $name): Command
    {
        $this->registerCommands();

        return parent::find($name);
    }

    public function get(string $name): Command
    {
        $this->registerCommands();

        return parent::get($name);
    }

    public function all(?string $namespace = null): array
    {
        $this->registerCommands();

        return parent::all($namespace);
    }

    public function getLongVersion(): string
    {
        return parent::getLongVersion().\sprintf(' (env: <comment>%s</>, debug: <comment>%s</>)', $this->kernel->getEnvironment(), $this->kernel->isDebug() ? 'true' : 'false');
    }

    public function addCommand(callable|Command $command): ?Command
    {
        $this->registerCommands();

        return parent::addCommand($command);
    }

    protected function registerCommands(): void
    {
        if ($this->commandsRegistered) {
            return;
        }

        $this->commandsRegistered = true;

        $this->kernel->boot();

        $container = $this->kernel->getContainer();

        foreach ($this->kernel->getBundles() as $bundle) {
            if ($bundle instanceof Bundle) {
                try {
                    $bundle->registerCommands($this);
                } catch (\Throwable $e) {
                    $this->registrationErrors[] = $e;
                }
            }
        }

        if ($container->has('console.command_loader')) {
            $this->setCommandLoader($container->get('console.command_loader'));
        }

        if ($container->hasParameter('console.command.ids')) {
            $lazyCommandIds = $container->hasParameter('console.lazy_command.ids') ? $container->getParameter('console.lazy_command.ids') : [];
            foreach ($container->getParameter('console.command.ids') as $id) {
                if (!isset($lazyCommandIds[$id])) {
                    try {
                        $this->addCommand($container->get($id));
                    } catch (\Throwable $e) {
                        $this->registrationErrors[] = $e;
                    }
                }
            }
        }
    }

    private function renderRegistrationErrors(InputInterface $input, OutputInterface $output): void
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        (new SymfonyStyle($input, $output))->warning('Some commands could not be registered:');

        foreach ($this->registrationErrors as $error) {
            $this->doRenderThrowable($error, $output);
        }
    }
}
