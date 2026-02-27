<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\ArgumentResolver\ArgumentResolver;
use Symfony\Component\Console\ArgumentResolver\ArgumentResolverInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Interaction\Interaction;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Represents an invokable command.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @internal
 */
class InvokableCommand implements SignalableCommandInterface
{
    private readonly ?SignalableCommandInterface $signalableCommand;
    private readonly \ReflectionFunction $invokable;
    /**
     * @var list<Interaction>|null
     */
    private ?array $interactions = null;
    private $code;

    public function __construct(
        private readonly Command $command,
        callable $code,
        private ?ArgumentResolverInterface $argumentResolver = null,
    ) {
        $this->code = $code;
        $this->signalableCommand = $code instanceof SignalableCommandInterface ? $code : null;
        $this->invokable = new \ReflectionFunction($this->getClosure($code));
    }

    /**
     * Invokes a callable with parameters generated from the input interface.
     */
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $statusCode = $this->invokable->invoke(...$this->getParameters($this->invokable, $input, $output));

        if (!\is_int($statusCode)) {
            throw new \TypeError(\sprintf('The command "%s" must return an integer value in the "%s" method, but "%s" was returned.', $this->command->getName(), $this->invokable->getName(), get_debug_type($statusCode)));
        }

        return $statusCode;
    }

    /**
     * Configures the input definition from an invokable-defined function.
     *
     * Processes the parameters of the reflection function to extract and
     * add arguments or options to the provided input definition.
     */
    public function configure(InputDefinition $definition): void
    {
        foreach ($this->invokable->getParameters() as $parameter) {
            if ($argument = Argument::tryFrom($parameter)) {
                $definition->addArgument($argument->toInputArgument());
                continue;
            }

            if ($option = Option::tryFrom($parameter)) {
                $definition->addOption($option->toInputOption());
                continue;
            }

            if ($input = MapInput::tryFrom($parameter)) {
                $inputArguments = array_map(static fn (Argument $a) => $a->toInputArgument(), iterator_to_array($input->getArguments(), false));

                // make sure optional arguments are defined after required ones
                usort($inputArguments, static fn (InputArgument $a, InputArgument $b) => (int) $b->isRequired() - (int) $a->isRequired());

                foreach ($inputArguments as $inputArgument) {
                    $definition->addArgument($inputArgument);
                }

                foreach ($input->getOptions() as $option) {
                    $definition->addOption($option->toInputOption());
                }
            }
        }
    }

    public function getCode(): callable
    {
        return $this->code;
    }

    private function getClosure(callable $code): \Closure
    {
        if (!$code instanceof \Closure) {
            return $code(...);
        }

        if (null !== (new \ReflectionFunction($code))->getClosureThis()) {
            return $code;
        }

        set_error_handler(static function () {});
        try {
            if ($c = \Closure::bind($code, $this->command)) {
                $code = $c;
            }
        } finally {
            restore_error_handler();
        }

        return $code;
    }

    private function getParameters(\ReflectionFunction $function, InputInterface $input, OutputInterface $output): array
    {
        $coreUtilities = [];
        $needsArgumentResolver = false;

        foreach ($function->getParameters() as $index => $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType) {
                $argument = match ($type->getName()) {
                    InputInterface::class => $input,
                    OutputInterface::class => $output,
                    SymfonyStyle::class => new SymfonyStyle($input, $output, $this->command->getApplication()?->getDispatcher()),
                    Cursor::class => new Cursor($output),
                    Application::class => $this->command->getApplication(),
                    Command::class, self::class => $this->command,
                    default => null,
                };

                if (null !== $argument) {
                    $coreUtilities[$index] = $argument;
                    continue;
                }
            }

            $needsArgumentResolver = true;
        }

        if (!$needsArgumentResolver) {
            return $coreUtilities;
        }

        if (null === $this->argumentResolver) {
            $this->argumentResolver = $this->command->getApplication()?->getArgumentResolver() ?? new ArgumentResolver(
                ArgumentResolver::getDefaultArgumentValueResolvers()
            );
        }

        $closure = $function->getClosure();
        $resolvedArgs = $this->argumentResolver->getArguments($input, $closure, $function);

        $parameters = [];
        $resolvedIndex = 0;

        foreach ($function->getParameters() as $index => $param) {
            if (isset($coreUtilities[$index])) {
                $parameters[] = $coreUtilities[$index];
            } elseif ($param->isVariadic()) {
                // Variadic parameters consume all remaining resolved arguments
                $parameters = [...$parameters, ...\array_slice($resolvedArgs, $resolvedIndex)];
                break;
            } else {
                $parameters[] = $resolvedArgs[$resolvedIndex++] ?? null;
            }
        }

        return $parameters;
    }

    public function getSubscribedSignals(): array
    {
        return $this->signalableCommand?->getSubscribedSignals() ?? [];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        return $this->signalableCommand?->handleSignal($signal, $previousExitCode) ?? false;
    }

    public function isInteractive(): bool
    {
        if (null === $this->interactions) {
            $this->collectInteractions();
        }

        return [] !== $this->interactions;
    }

    public function interact(InputInterface $input, OutputInterface $output): void
    {
        if (null === $this->interactions) {
            $this->collectInteractions();
        }

        foreach ($this->interactions as $interaction) {
            $interaction->interact($input, $output, $this->getParameters(...));
        }
    }

    private function collectInteractions(): void
    {
        $invokableThis = $this->invokable->getClosureThis();

        $this->interactions = [];
        foreach ($this->invokable->getParameters() as $parameter) {
            if ($spec = Argument::tryFrom($parameter)) {
                if ($attribute = $spec->getInteractiveAttribute()) {
                    $this->interactions[] = new Interaction($invokableThis, $attribute);
                }

                continue;
            }

            if ($spec = MapInput::tryFrom($parameter)) {
                $this->interactions = [...$this->interactions, ...$spec->getPropertyInteractions(), ...$spec->getMethodInteractions()];
            }
        }

        if (!$class = $this->invokable->getClosureCalledClass()) {
            return;
        }

        foreach ($class->getMethods() as $method) {
            if ($attribute = Interact::tryFrom($method)) {
                $this->interactions[] = new Interaction($invokableThis, $attribute);
            }
        }
    }
}
