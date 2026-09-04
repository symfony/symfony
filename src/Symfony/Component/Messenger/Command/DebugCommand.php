<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

/**
 * A console command to debug Messenger information.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
#[AsCommand(name: 'debug:messenger', description: 'List messages you can dispatch using the message buses')]
class DebugCommand extends Command
{
    /**
     * @param array<string, array<string, list<array{0: string, 1: array}>>> $mapping
     * @param array<string, list<string>>                                    $sendersMap
     * @param array<string, string>                                          $senderAliases
     * @param array<string, list<string>>                                    $attributeMessages Message classes discovered via #[AsMessage] attribute mapped to their transports
     * @param array<string, string>                                          $failureTransports Failure transports mapped by source transport
     */
    public function __construct(
        private array $mapping,
        private readonly array $sendersMap = [],
        private readonly array $senderAliases = [],
        private readonly array $attributeMessages = [],
        private readonly array $failureTransports = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bus', InputArgument::OPTIONAL, \sprintf('The bus id (one of "%s")', implode('", "', array_keys($this->mapping))))
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'A message FQCN to inspect')
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command displays all messages that can be
                dispatched using the message buses, their handlers, routing rules and
                failure transports:

                  <info>php %command.full_name%</info>

                Or for a specific bus only:

                  <info>php %command.full_name% command_bus</info>

                Or inspect what happens when dispatching a specific message:

                  <info>php %command.full_name% --message='App\Message\MyMessage'</info>

                Routing is based on configuration and #[AsMessage] attributes.
                TransportNamesStamp can override this routing at dispatch time.

                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Messenger');

        $messageClass = $input->getOption('message');
        if (null !== $messageClass) {
            $messageClass = ltrim($messageClass, '\\');

            if (!class_exists($messageClass) && !interface_exists($messageClass)) {
                throw new RuntimeException(\sprintf('Message class "%s" does not exist.', $messageClass));
            }
        }

        $mapping = $this->mapping;
        if ($bus = $input->getArgument('bus')) {
            if (!isset($mapping[$bus])) {
                throw new RuntimeException(\sprintf('Bus "%s" does not exist. Known buses are "%s".', $bus, implode('", "', array_keys($this->mapping))));
            }
            $mapping = [$bus => $mapping[$bus]];
        }

        foreach ($mapping as $bus => $handlersByMessage) {
            $io->section($bus);

            if (null !== $messageClass) {
                $handlersByMessage = [$messageClass => $this->getHandlersForMessage($messageClass, $handlersByMessage)];
            }

            $tableRows = [];
            foreach ($handlersByMessage as $message => $handlers) {
                if ($description = self::getClassDescription($message)) {
                    $tableRows[] = [\sprintf('<comment>%s</>', $description)];
                }

                $tableRows[] = [\sprintf('<fg=cyan>%s</fg=cyan>', $message)];
                foreach ($handlers as $handler) {
                    $tableRows[] = [
                        \sprintf('    handled by <info>%s</>', $handler[0]).$this->formatConditions($handler[1], $handler[0]),
                    ];
                    if ($handlerDescription = self::getClassDescription($handler[0])) {
                        $tableRows[] = [\sprintf('               <comment>%s</>', $handlerDescription)];
                    }
                }

                if (!$handlers) {
                    $tableRows[] = ['    <comment>not handled</>'];
                }

                $transportNames = $this->getTransportNamesForMessage($message);
                foreach ($transportNames as $transportName) {
                    $tableRows[] = [\sprintf('    routed to <info>%s</>', $transportName)];
                }
                if (!$transportNames && null !== $messageClass) {
                    $tableRows[] = ['    <comment>not routed</>'];
                }
                $tableRows[] = [''];
            }

            if ($tableRows) {
                $io->text('The following messages can be dispatched:');
                $io->newLine();
                $io->table([], $tableRows);
            } else {
                $io->warning(\sprintf('No handled message found in bus "%s".', $bus));
            }
        }

        $this->displayTransportRules($io, $messageClass);

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('bus')) {
            $suggestions->suggestValues(array_keys($this->mapping));
        }

        if ($input->mustSuggestOptionValuesFor('message')) {
            $messages = array_keys($this->sendersMap + $this->attributeMessages);
            foreach ($this->mapping as $handlersByMessage) {
                $messages = array_merge($messages, array_keys($handlersByMessage));
            }

            $messages = array_values(array_unique(array_filter($messages, static fn (string $message): bool => class_exists($message) || interface_exists($message))));
            sort($messages);

            $suggestions->suggestValues($messages);
        }
    }

    private function formatConditions(array $options, string $serviceId): string
    {
        // the alias MessengerPass generates is the service id, which is already displayed as the handler
        if ($serviceId === ($options['alias'] ?? null)) {
            unset($options['alias']);
        }

        if (!$options) {
            return '';
        }

        $optionsMapping = [];
        foreach ($options as $key => $value) {
            $optionsMapping[] = $key.'='.$value;
        }

        return ' (when '.implode(', ', $optionsMapping).')';
    }

    private static function getClassDescription(string $class): string
    {
        try {
            $r = new \ReflectionClass($class);

            if ($docComment = $r->getDocComment()) {
                $docComment = preg_split('#\n\s*\*\s*[\n@]#', substr($docComment, 3, -2), 2)[0];

                return trim(preg_replace('#\s*\n\s*\*\s*#', ' ', $docComment));
            }
        } catch (\ReflectionException) {
        }

        return '';
    }

    /**
     * @param array<string, list<array{0: string, 1: array}>> $handlersByMessage
     *
     * @return list<array{0: string, 1: array}>
     */
    private function getHandlersForMessage(string $message, array $handlersByMessage): array
    {
        $handlers = [];
        $seen = [];

        foreach (HandlersLocator::listTypesForClass($message) as $type) {
            foreach ($handlersByMessage[$type] ?? [] as $handler) {
                $options = $handler[1];
                $name = $handler[0].'::'.($options['method'] ?? '__invoke').'@'.($options['alias'] ?? '');
                if (isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
                $handlers[] = $handler;
            }
        }

        return $handlers;
    }

    /**
     * @return list<string>
     */
    private function getTransportNamesForMessage(string $message): array
    {
        if (!class_exists($message) && !interface_exists($message)) {
            return [];
        }

        $serviceToAlias = array_flip($this->senderAliases);
        $transportNames = [];

        foreach (SendersLocator::getSenderAliases($message, $this->sendersMap) as $senderAlias) {
            $transportName = $serviceToAlias[$senderAlias] ?? $senderAlias;
            if (!\in_array($transportName, $transportNames, true)) {
                $transportNames[] = $transportName;
            }
        }

        sort($transportNames);

        return $transportNames;
    }

    private function displayTransportRules(SymfonyStyle $io, ?string $messageClass): void
    {
        $rulesByTransport = $this->getRulesByTransport($messageClass);
        if (!$rulesByTransport && null === $messageClass) {
            return;
        }

        $io->section('Transports');
        $io->info('TransportNamesStamp can override this routing at dispatch time.');

        if (!$rulesByTransport) {
            $io->text($this->senderAliases ? 'No routing rules apply to this message.' : 'No transports are configured.');

            return;
        }

        foreach ($rulesByTransport as $transportName => $rules) {
            $io->writeln(\sprintf('<fg=cyan>%s</>', $transportName));
            if (!$rules) {
                $io->writeln('    <comment>No routing rules.</>');
            } else {
                foreach ($rules as [$rule, $fromAttribute]) {
                    $io->writeln('    '.$this->formatRoutingRule($rule, $fromAttribute));
                }
            }
            if ($failureTransport = $this->getFailureTransportName($transportName)) {
                $io->writeln(\sprintf('    failed messages are routed to <info>%s</>', $failureTransport));
            }
            $io->newLine();
        }
    }

    /**
     * @return array<string, list<array{0: string, 1: bool}>>
     */
    private function getRulesByTransport(?string $messageClass): array
    {
        $serviceToAlias = array_flip($this->senderAliases);

        if (null !== $messageClass) {
            return $this->getRulesByTransportForMessage($messageClass, $serviceToAlias);
        }

        $rulesByTransport = array_fill_keys(array_keys($this->senderAliases), []);

        foreach ($this->sendersMap as $rule => $senders) {
            foreach ($senders as $sender) {
                $transportName = $serviceToAlias[$sender] ?? $sender;
                $rulesByTransport[$transportName][] = [$rule, false];
            }
        }

        foreach ($this->attributeMessages as $message => $transports) {
            if ($this->hasConfigurationRouting($message)) {
                continue;
            }

            foreach ($transports as $transport) {
                $transportName = $serviceToAlias[$transport] ?? $transport;
                if (!\in_array([$message, true], $rulesByTransport[$transportName] ?? [], true)) {
                    $rulesByTransport[$transportName][] = [$message, true];
                }
            }
        }

        ksort($rulesByTransport);
        foreach ($rulesByTransport as &$rules) {
            usort($rules, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        }
        unset($rules);

        return $rulesByTransport;
    }

    /**
     * @param array<string, string> $serviceToAlias
     *
     * @return array<string, list<array{0: string, 1: bool}>>
     */
    private function getRulesByTransportForMessage(string $messageClass, array $serviceToAlias): array
    {
        $effectiveSenderAliases = SendersLocator::getSenderAliases($messageClass, $this->sendersMap);
        $rulesByTransport = [];
        $seen = [];

        if ($this->hasConfigurationRouting($messageClass)) {
            foreach (HandlersLocator::listTypesForClass($messageClass) as $rule) {
                if (str_ends_with($rule, '*') && $seen) {
                    continue;
                }

                foreach ($this->sendersMap[$rule] ?? [] as $senderAlias) {
                    if (isset($seen[$senderAlias]) || !\in_array($senderAlias, $effectiveSenderAliases, true)) {
                        continue;
                    }

                    $seen[$senderAlias] = true;
                    $transportName = $serviceToAlias[$senderAlias] ?? $senderAlias;
                    $rulesByTransport[$transportName][] = [$rule, false];
                }
            }
        } else {
            foreach ([$messageClass] + class_parents($messageClass) + class_implements($messageClass) as $rule) {
                foreach (self::getTransportsFromAttribute($rule) as $senderAlias) {
                    if (isset($seen[$senderAlias]) || !\in_array($senderAlias, $effectiveSenderAliases, true)) {
                        continue;
                    }

                    $seen[$senderAlias] = true;
                    $transportName = $serviceToAlias[$senderAlias] ?? $senderAlias;
                    $rulesByTransport[$transportName][] = [$rule, true];
                }
            }
        }

        ksort($rulesByTransport);

        return $rulesByTransport;
    }

    /**
     * @return list<string>
     */
    private static function getTransportsFromAttribute(string $class): array
    {
        $transports = [];

        foreach ((new \ReflectionClass($class))->getAttributes(AsMessage::class, \ReflectionAttribute::IS_INSTANCEOF) as $refAttr) {
            $transports = array_merge($transports, (array) ($refAttr->newInstance()->transport ?? []));
        }

        return $transports;
    }

    private function hasConfigurationRouting(string $message): bool
    {
        foreach (HandlersLocator::listTypesForClass($message) as $type) {
            if ($this->sendersMap[$type] ?? []) {
                return true;
            }
        }

        return false;
    }

    private function getFailureTransportName(string $transportName): ?string
    {
        if (!$failureTransport = $this->failureTransports[$transportName] ?? null) {
            return null;
        }

        $failureTransport = array_flip($this->senderAliases)[$failureTransport] ?? $failureTransport;

        return $failureTransport === $transportName ? null : $failureTransport;
    }

    private function formatRoutingRule(string $rule, bool $fromAttribute): string
    {
        $descriptions = [];
        if ('*' === $rule) {
            $descriptions[] = 'fallback for messages with no other route';
        } elseif (str_ends_with($rule, '\\*')) {
            $descriptions[] = 'namespace';
        } elseif (interface_exists($rule)) {
            $descriptions[] = 'interface, matches implementers';
        }

        if ($fromAttribute) {
            $descriptions[] = 'from #[AsMessage]';
        }

        return $rule.($descriptions ? ' ('.implode(', ', $descriptions).')' : '');
    }
}
