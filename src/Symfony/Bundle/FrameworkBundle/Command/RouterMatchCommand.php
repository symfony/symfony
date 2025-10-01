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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;
use Symfony\Component\Routing\RouterInterface;

/**
 * A console command to test route matching.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
#[AsCommand(name: 'router:match', description: 'Help debug routes by simulating a path info match')]
class RouterMatchCommand extends Command
{
    /**
     * @param iterable<mixed, ExpressionFunctionProviderInterface> $expressionLanguageProviders
     */
    public function __construct(
        private RouterInterface $router,
        private iterable $expressionLanguageProviders = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('path_info', InputArgument::REQUIRED, 'A path info'),
                new InputOption('method', null, InputOption::VALUE_REQUIRED, 'Set the HTTP method'),
                new InputOption('scheme', null, InputOption::VALUE_REQUIRED, 'Set the URI scheme (usually http or https)'),
                new InputOption('host', null, InputOption::VALUE_REQUIRED, 'Set the URI host'),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> shows which routes match a given request and which don't and for what reason:

                  <info>php %command.full_name% /foo</info>

                or

                  <info>php %command.full_name% /foo --method POST --scheme https --host symfony.com --verbose</info>

                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = $this->router->getContext();
        if (null !== $method = $input->getOption('method')) {
            $context->setMethod($method);
        }
        if (null !== $scheme = $input->getOption('scheme')) {
            $context->setScheme($scheme);
        }
        if (null !== $host = $input->getOption('host')) {
            $context->setHost($host);
        }

        $matcher = new TraceableUrlMatcher($this->router->getRouteCollection(), $context);
        foreach ($this->expressionLanguageProviders as $provider) {
            $matcher->addExpressionLanguageProvider($provider);
        }

        $traces = $matcher->getTraces($input->getArgument('path_info'));

        $io->newLine();

        $matches = false;
        foreach ($traces as $trace) {
            if (TraceableUrlMatcher::ROUTE_ALMOST_MATCHES == $trace['level']) {
                $io->text(\sprintf('Route <info>"%s"</> almost matches but %s', $trace['name'], lcfirst($trace['log'])));
            } elseif (TraceableUrlMatcher::ROUTE_MATCHES == $trace['level']) {
                $io->success(\sprintf('Route "%s" matches', $trace['name']));

                $routerDebugCommand = $this->getApplication()->find('debug:router');
                $routerDebugCommand->run(new ArrayInput(['name' => $trace['name']]), $output);

                $matches = true;
            } elseif ($input->getOption('verbose')) {
                $io->text(\sprintf('Route "%s" does not match: %s', $trace['name'], $trace['log']));
            }
        }

        if (!$matches) {
            $io->error(\sprintf('None of the routes match the path "%s"', $input->getArgument('path_info')));

            return 1;
        }

        return 0;
    }
}
