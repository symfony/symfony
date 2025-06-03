<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\Internal\MissingDotenv;
use Symfony\Component\Runtime\Internal\SymfonyErrorHandler;
use Symfony\Component\Runtime\Runner\Symfony\ConsoleApplicationRunner;
use Symfony\Component\Runtime\Runner\Symfony\HttpKernelRunner;
use Symfony\Component\Runtime\Runner\Symfony\ResponseRunner;

// Help opcache.preload discover always-needed symbols
class_exists(MissingDotenv::class, false) || class_exists(Dotenv::class) || class_exists(MissingDotenv::class);

/**
 * Knows the basic conventions to run Symfony apps.
 *
 * In addition to the options managed by GenericRuntime, it accepts the following options:
 *  - "env" to define the name of the environment the app runs in;
 *  - "disable_dotenv" to disable looking for .env files;
 *  - "dotenv_path" to define the path of dot-env files - defaults to ".env";
 *  - "prod_envs" to define the names of the production envs - defaults to ["prod"];
 *  - "test_envs" to define the names of the test envs - defaults to ["test"];
 *  - "use_putenv" to tell Dotenv to set env vars using putenv() (NOT RECOMMENDED.)
 *  - "dotenv_overload" to tell Dotenv to override existing vars
 *
 * When the "debug" / "env" options are not defined, they will fallback to the
 * "APP_DEBUG" / "APP_ENV" environment variables, and to the "--env|-e" / "--no-debug"
 * command line arguments if "symfony/console" is installed.
 *
 * When the "symfony/dotenv" component is installed, .env files are loaded.
 * When "symfony/error-handler" is installed, it is registered in debug mode.
 *
 * On top of the base arguments provided by GenericRuntime,
 * this runtime can feed the app-callable with arguments of type:
 *  - Request from "symfony/http-foundation" if the component is installed;
 *  - Application, Command, InputInterface and/or OutputInterface
 *    from "symfony/console" if the component is installed.
 *
 * This runtime can handle app-callables that return instances of either:
 *  - HttpKernelInterface,
 *  - Response,
 *  - Application,
 *  - Command,
 *  - int|string|null as handled by GenericRuntime.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SymfonyRuntime extends GenericRuntime
{
    private readonly ArgvInput $input;
    private readonly ConsoleOutput $output;
    private readonly Application $console;
    private readonly Command $command;

    /**
     * @param array {
     *   debug?: ?bool,
     *   env?: ?string,
     *   disable_dotenv?: ?bool,
     *   project_dir?: ?string,
     *   prod_envs?: ?string[],
     *   dotenv_path?: ?string,
     *   test_envs?: ?string[],
     *   use_putenv?: ?bool,
     *   runtimes?: ?array,
     *   error_handler?: string|false,
     *   env_var_name?: string,
     *   debug_var_name?: string,
     *   dotenv_overload?: ?bool,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $envKey = $options['env_var_name'] ??= 'APP_ENV';
        $debugKey = $options['debug_var_name'] ??= 'APP_DEBUG';

        if (isset($options['env'])) {
            $_SERVER[$envKey] = $options['env'];
        } elseif (empty($_GET) && isset($_SERVER['argv']) && class_exists(ArgvInput::class)) {
            $this->options = $options;
            $this->getInput();
        }

        if (!($options['disable_dotenv'] ?? false) && isset($options['project_dir']) && !class_exists(MissingDotenv::class, false)) {
            (new Dotenv($envKey, $debugKey))
                ->setProdEnvs((array) ($options['prod_envs'] ?? ['prod']))
                ->usePutenv($options['use_putenv'] ?? false)
                ->bootEnv($options['project_dir'].'/'.($options['dotenv_path'] ?? '.env'), 'dev', (array) ($options['test_envs'] ?? ['test']), $options['dotenv_overload'] ?? false);

            if (isset($this->input) && ($options['dotenv_overload'] ?? false)) {
                if ($this->input->getParameterOption(['--env', '-e'], $_SERVER[$envKey], true) !== $_SERVER[$envKey]) {
                    throw new \LogicException(sprintf('Cannot use "--env" or "-e" when the "%s" file defines "%s" and the "dotenv_overload" runtime option is true.', $options['dotenv_path'] ?? '.env', $envKey));
                }

                if ($_SERVER[$debugKey] && $this->input->hasParameterOption('--no-debug', true)) {
                    putenv($debugKey.'='.$_SERVER[$debugKey] = $_ENV[$debugKey] = '0');
                }
            }

            $options['debug'] ??= '1' === $_SERVER[$debugKey];
            $options['disable_dotenv'] = true;
        } else {
            $_SERVER[$envKey] ??= $_ENV[$envKey] ?? 'dev';
            $_SERVER[$debugKey] ??= $_ENV[$debugKey] ?? !\in_array($_SERVER[$envKey], (array) ($options['prod_envs'] ?? ['prod']), true);
        }

        $options['error_handler'] ??= SymfonyErrorHandler::class;

        parent::__construct($options);
    }

    public function getRunner(?object $application): RunnerInterface
    {
        if ($application instanceof HttpKernelInterface) {
            return new HttpKernelRunner($application, Request::createFromGlobals(), $this->options['debug'] ?? false);
        }

        if ($application instanceof Response) {
            return new ResponseRunner($application);
        }

        if ($application instanceof Command) {
            $console = $this->console ??= new Application();
            $console->setName($application->getName() ?: $console->getName());

            if (!$application->getName() || !$console->has($application->getName())) {
                $application->setName($_SERVER['argv'][0]);
                $console->add($application);
            }

            $console->setDefaultCommand($application->getName(), true);
            $console->getDefinition()->addOptions($application->getDefinition()->getOptions());

            return $this->getRunner($console);
        }

        if ($application instanceof Application) {
            if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
                echo 'Warning: The console should be invoked via the CLI version of PHP, not the '.\PHP_SAPI.' SAPI'.\PHP_EOL;
            }

            set_time_limit(0);
            $defaultEnv = !isset($this->options['env']) ? ($_SERVER[$this->options['env_var_name']] ?? 'dev') : null;
            $output = $this->output ??= new ConsoleOutput();

            return new ConsoleApplicationRunner($application, $defaultEnv, $this->getInput(), $output);
        }

        if (isset($this->command)) {
            $this->getInput()->bind($this->command->getDefinition());
        }

        return parent::getRunner($application);
    }

    protected function getArgument(\ReflectionParameter $parameter, ?string $type): mixed
    {
        return match ($type) {
            Request::class => Request::createFromGlobals(),
            InputInterface::class => $this->getInput(),
            OutputInterface::class => $this->output ??= new ConsoleOutput(),
            Application::class => $this->console ??= new Application(),
            Command::class => $this->command ??= new Command(),
            default => parent::getArgument($parameter, $type),
        };
    }

    protected static function register(GenericRuntime $runtime): GenericRuntime
    {
        $self = new self($runtime->options + ['runtimes' => []]);
        $self->options['runtimes'] += [
            HttpKernelInterface::class => $self,
            Request::class => $self,
            Response::class => $self,
            Application::class => $self,
            Command::class => $self,
            InputInterface::class => $self,
            OutputInterface::class => $self,
        ];
        $runtime->options = $self->options;

        return $self;
    }

    private function getInput(): ArgvInput
    {
        if (!empty($_GET) && filter_var(ini_get('register_argc_argv'), \FILTER_VALIDATE_BOOL)) {
            throw new \Exception('CLI applications cannot be run safely on non-CLI SAPIs with register_argc_argv=On.');
        }

        if (isset($this->input)) {
            return $this->input;
        }

        $input = new ArgvInput();

        if (isset($this->options['env'])) {
            return $this->input = $input;
        }

        if (null !== $env = $input->getParameterOption(['--env', '-e'], null, true)) {
            putenv($this->options['env_var_name'].'='.$_SERVER[$this->options['env_var_name']] = $_ENV[$this->options['env_var_name']] = $env);
        }

        if ($input->hasParameterOption('--no-debug', true)) {
            putenv($this->options['debug_var_name'].'='.$_SERVER[$this->options['debug_var_name']] = $_ENV[$this->options['debug_var_name']] = '0');
        }

        return $this->input = $input;
    }
}
