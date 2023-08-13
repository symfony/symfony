<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Command;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Security\LazyFirewallContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

/**
 * @author Timo Bakx <timobakx@gmail.com>
 */
#[AsCommand(name: 'debug:firewall', description: 'Display information about your security firewall(s)')]
final class DebugFirewallCommand extends Command
{
    private array $firewallNames;
    private ContainerInterface $contexts;
    private ContainerInterface $eventDispatchers;
    private array $authenticators;

    /**
     * @param string[]                   $firewallNames
     * @param AuthenticatorInterface[][] $authenticators
     */
    public function __construct(array $firewallNames, ContainerInterface $contexts, ContainerInterface $eventDispatchers, array $authenticators)
    {
        $this->firewallNames = $firewallNames;
        $this->contexts = $contexts;
        $this->eventDispatchers = $eventDispatchers;
        $this->authenticators = $authenticators;

        parent::__construct();
    }

    protected function configure(): void
    {
        $exampleName = $this->getExampleName();

        $this
            ->setHelp(<<<EOF
The <info>%command.name%</info> command displays the firewalls that are configured
in your application:

  <info>php %command.full_name%</info>

You can pass a firewall name to display more detailed information about
a specific firewall:

  <info>php %command.full_name% $exampleName</info>

To include all events and event listeners for a specific firewall, use the
<info>events</info> option:

  <info>php %command.full_name% --events $exampleName</info>

EOF
            )
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, sprintf('A firewall name (for example "%s")', $exampleName)),
                new InputOption('events', null, InputOption::VALUE_NONE, 'Include a list of event listeners (only available in combination with the "name" argument)'),
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');

        if (null === $name) {
            $this->displayFirewallList($io);

            return 0;
        }

        $serviceId = sprintf('security.firewall.map.context.%s', $name);

        if (!$this->contexts->has($serviceId)) {
            $io->error(sprintf('Firewall %s was not found. Available firewalls are: %s', $name, implode(', ', $this->firewallNames)));

            return 1;
        }

        /** @var FirewallContext $context */
        $context = $this->contexts->get($serviceId);

        $io->title(sprintf('Firewall "%s"', $name));

        $this->displayFirewallSummary($name, $context, $io);

        $this->displaySwitchUser($context, $io);

        if ($input->getOption('events')) {
            $this->displayEventListeners($name, $context, $io);
        }

        $this->displayAuthenticators($name, $io);

        return 0;
    }

    protected function displayFirewallList(SymfonyStyle $io): void
    {
        $io->title('Firewalls');
        $io->text('The following firewalls are defined:');

        $io->listing($this->firewallNames);

        $io->comment(sprintf('To view details of a specific firewall, re-run this command with a firewall name. (e.g. <comment>debug:firewall %s</comment>)', $this->getExampleName()));
    }

    protected function displayFirewallSummary(string $name, FirewallContext $context, SymfonyStyle $io): void
    {
        if (null === $context->getConfig()) {
            return;
        }

        $rows = [
            ['Name', $name],
            ['Context', $context->getConfig()->getContext()],
            ['Lazy', $context instanceof LazyFirewallContext ? 'Yes' : 'No'],
            ['Stateless', $context->getConfig()->isStateless() ? 'Yes' : 'No'],
            ['User Checker', $context->getConfig()->getUserChecker()],
            ['Provider', $context->getConfig()->getProvider()],
            ['Entry Point', $context->getConfig()->getEntryPoint()],
            ['Access Denied URL', $context->getConfig()->getAccessDeniedUrl()],
            ['Access Denied Handler', $context->getConfig()->getAccessDeniedHandler()],
        ];

        $io->table(
            ['Option', 'Value'],
            $rows
        );
    }

    private function displaySwitchUser(FirewallContext $context, SymfonyStyle $io): void
    {
        if ((null === $config = $context->getConfig()) || (null === $switchUser = $config->getSwitchUser())) {
            return;
        }

        $io->section('User switching');

        $io->table(['Option', 'Value'], [
            ['Parameter', $switchUser['parameter'] ?? ''],
            ['Provider', $switchUser['provider'] ?? $config->getProvider()],
            ['User Role', $switchUser['role'] ?? ''],
        ]);
    }

    protected function displayEventListeners(string $name, FirewallContext $context, SymfonyStyle $io): void
    {
        $io->title(sprintf('Event listeners for firewall "%s"', $name));

        $dispatcherId = sprintf('security.event_dispatcher.%s', $name);

        if (!$this->eventDispatchers->has($dispatcherId)) {
            $io->text('No event dispatcher has been registered for this firewall.');

            return;
        }

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->eventDispatchers->get($dispatcherId);

        foreach ($dispatcher->getListeners() as $event => $listeners) {
            $io->section(sprintf('"%s" event', $event));

            $rows = [];
            foreach ($listeners as $order => $listener) {
                $rows[] = [
                    sprintf('#%d', $order + 1),
                    $this->formatCallable($listener),
                    $dispatcher->getListenerPriority($event, $listener),
                ];
            }

            $io->table(
                ['Order', 'Callable', 'Priority'],
                $rows
            );
        }
    }

    private function displayAuthenticators(string $name, SymfonyStyle $io): void
    {
        $io->title(sprintf('Authenticators for firewall "%s"', $name));

        $authenticators = $this->authenticators[$name] ?? [];

        if (0 === \count($authenticators)) {
            $io->text('No authenticators have been registered for this firewall.');

            return;
        }

        $io->table(
            ['Classname'],
            array_map(
                fn ($authenticator) => [$authenticator::class],
                $authenticators
            )
        );
    }

    private function formatCallable(mixed $callable): string
    {
        if (\is_array($callable)) {
            if (\is_object($callable[0])) {
                return sprintf('%s::%s()', $callable[0]::class, $callable[1]);
            }

            return sprintf('%s::%s()', $callable[0], $callable[1]);
        }

        if (\is_string($callable)) {
            return sprintf('%s()', $callable);
        }

        if ($callable instanceof \Closure) {
            $r = new \ReflectionFunction($callable);
            if (str_contains($r->name, '{closure}')) {
                return 'Closure()';
            }
            if ($class = \PHP_VERSION_ID >= 80111 ? $r->getClosureCalledClass() : $r->getClosureScopeClass()) {
                return sprintf('%s::%s()', $class->name, $r->name);
            }

            return $r->name.'()';
        }

        if (method_exists($callable, '__invoke')) {
            return sprintf('%s::__invoke()', $callable::class);
        }

        throw new \InvalidArgumentException('Callable is not describable.');
    }

    private function getExampleName(): string
    {
        $name = 'main';

        if (!\in_array($name, $this->firewallNames, true)) {
            $name = reset($this->firewallNames);
        }

        return $name;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues($this->firewallNames);
        }
    }
}
