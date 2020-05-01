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
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\Internal\MissingDotenv;
use Symfony\Component\Runtime\Internal\MissingErrorHandler;
use Symfony\Component\Runtime\ResolvedApp\Symfony\CommandResolved;
use Symfony\Component\Runtime\StartedApp\Symfony\ApplicationStarted;
use Symfony\Component\Runtime\StartedApp\Symfony\HttpKernelStarted;
use Symfony\Component\Runtime\StartedApp\Symfony\ResponseStarted;

// Help opcache.preload discover always-needed symbols
class_exists(ResponseStarted::class);
class_exists(HttpKernelStarted::class);
class_exists(MissingDotenv::class, false) || class_exists(Dotenv::class) || class_exists(MissingDotenv::class);
class_exists(MissingErrorHandler::class, false) || class_exists(ErrorHandler::class) || class_exists(MissingErrorHandler::class);

/**
 * Knows the basic conventions to run Symfony apps.
 *
 * Accepts two options:
 *  - "debug" to toggle debugging features;
 *  - "env" to define the name of the environment the app runs in.
 *
 * When these options are not defined, they will fallback:
 *  - to reading the "APP_DEBUG" and "APP_ENV" environment variables;
 *  - to parsing the "--env|-e" and "--no-debug" command line arguments
 *    if the "symfony/console" component is installed.
 *
 * When the "symfony/dotenv" component is installed, .env files are loaded.
 * When "symfony/error-handler" is installed, it is used to improve error handling.
 *
 * On top of the base arguments provided by the parent runtime,
 * this runtime can feed the app-closure with arguments of type:
 *  - Request from "symfony/http-foundation" if the component is installed;
 *  - Application, Command, InputInterface and/or OutputInterface
 *    from "symfony/console" if the component is installed.
 *
 * This runtime can handle app-closures that return instances of either:
 *  - HttpKernelInterface,
 *  - Response,
 *  - Application,
 *  - Command,
 *  - or Closure(): int
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SymfonyRuntime extends BaseRuntime
{
    private $request;
    private $input;
    private $output;
    private $application;
    private $command;
    private $env;

    public function __construct(array $options = [])
    {
        $this->env = $options['env'] ?? null;
        $_SERVER['APP_ENV'] = $options['env'] ?? $_SERVER['APP_ENV'] ?? null;

        parent::__construct($options);

        if (isset($_SERVER['argv']) && null === $this->env && class_exists(ArgvInput::class)) {
            $this->getInput();
        }

        if (class_exists(MissingDotenv::class, false) || !isset($options['project_dir'])) {
            $_SERVER['APP_DEBUG'] = filter_var($options['debug'] ?? $_SERVER['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        } else {
            (new Dotenv())->bootEnv($options['project_dir'].'/.env');
        }

        if (class_exists(MissingErrorHandler::class, false)) {
            // no-op
        } elseif ($_SERVER['APP_DEBUG']) {
            umask(0000);
            Debug::enable();
        } else {
            ErrorHandler::register();
        }
    }

    public function resolve(\Closure $app): ResolvedAppInterface
    {
        $app = parent::resolve($app);

        return $this->command ? new CommandResolved($this->command, $app) : $app;
    }

    public function start(object $app): StartedAppInterface
    {
        if ($app instanceof HttpKernelInterface) {
            $request = $this->request ?? $this->request = Request::createFromGlobals();

            return new HttpKernelStarted($app, $request, $this);
        }

        if ($app instanceof Response) {
            return new ResponseStarted($app);
        }

        if ($app instanceof Command) {
            $application = $this->application ?? $this->application = new Application();
            $application->setName($app->getName() ?: $application->getName());

            if (!$app->getName() || !$application->has($app->getName())) {
                $app->setName($_SERVER['argv'][0]);
                $application->add($app);
            }

            $application->setDefaultCommand($app->getName(), true);

            return $this->start($application);
        }

        if ($app instanceof Application) {
            if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
                echo 'Warning: The console should be invoked via the CLI version of PHP, not the '.\PHP_SAPI.' SAPI'.PHP_EOL;
            }

            set_time_limit(0);
            $defaultEnv = null === $this->env ? ($_SERVER['APP_ENV'] ?? 'dev') : null;

            return new ApplicationStarted($app, $defaultEnv, $this->getInput(), $this->output);
        }

        return parent::start($app);
    }

    protected function getArgument(\ReflectionParameter $parameter, ?\ReflectionType $type)
    {
        if (!$type instanceof \ReflectionNamedType) {
            return parent::getArgument($parameter, $type);
        }

        switch ($type->getName()) {
            case Request::class:
                return $this->request ?? $this->request = Request::createFromGlobals();

            case InputInterface::class:
                return $this->getInput();

            case OutputInterface::class:
                return $this->output ?? $this->output = new ConsoleOutput();

            case Application::class:
                return $this->application ?? $this->application = new Application();

            case Command::class:
                return $this->command ?? $this->command = new Command();
        }

        return parent::getArgument($parameter, $type);
    }

    private function getInput(): ArgvInput
    {
        if (null !== $this->input) {
            return $this->input;
        }

        $input = new ArgvInput();

        if (null !== $this->env) {
            return $this->input = $input;
        }

        if (null !== $env = $input->getParameterOption(['--env', '-e'], null, true)) {
            putenv('APP_ENV='.$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $env);
        }

        if ($input->hasParameterOption('--no-debug', true)) {
            putenv('APP_DEBUG='.$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0');
        }

        return $this->input = $input;
    }
}
