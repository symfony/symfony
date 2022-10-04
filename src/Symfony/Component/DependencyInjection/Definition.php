<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

/**
 * Definition represents a service definition.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Definition
{
    private const DEFAULT_DEPRECATION_TEMPLATE = 'The "%service_id%" service is deprecated. You should stop using it, as it will be removed in the future.';

    private ?string $class = null;
    private ?string $file = null;
    private string|array|null $factory = null;
    private bool $shared = true;
    private array $deprecation = [];
    private array $properties = [];
    private array $calls = [];
    private array $instanceof = [];
    private bool $autoconfigured = false;
    private string|array|null $configurator = null;
    private array $tags = [];
    private bool $public = false;
    private bool $synthetic = false;
    private bool $abstract = false;
    private bool $lazy = false;
    private ?array $decoratedService = null;
    private bool $autowired = false;
    private array $changes = [];
    private array $bindings = [];
    private array $errors = [];

    protected $arguments = [];

    /**
     * @internal
     *
     * Used to store the name of the inner id when using service decoration together with autowiring
     */
    public ?string $innerServiceId = null;

    /**
     * @internal
     *
     * Used to store the behavior to follow when using service decoration and the decorated service is invalid
     */
    public ?int $decorationOnInvalid = null;

    public function __construct(string $class = null, array $arguments = [])
    {
        if (null !== $class) {
            $this->setClass($class);
        }
        $this->arguments = $arguments;
    }

    /**
     * Returns all changes tracked for the Definition object.
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Sets the tracked changes for the Definition object.
     *
     * @param array $changes An array of changes for this Definition
     *
     * @return $this
     */
    public function setChanges(array $changes): static
    {
        $this->changes = $changes;

        return $this;
    }

    /**
     * Sets a factory.
     *
     * @param string|array|Reference|null $factory A PHP function, reference or an array containing a class/Reference and a method to call
     *
     * @return $this
     */
    public function setFactory(string|array|Reference|null $factory): static
    {
        $this->changes['factory'] = true;

        if (\is_string($factory) && str_contains($factory, '::')) {
            $factory = explode('::', $factory, 2);
        } elseif ($factory instanceof Reference) {
            $factory = [$factory, '__invoke'];
        }

        $this->factory = $factory;

        return $this;
    }

    /**
     * Gets the factory.
     *
     * @return string|array|null The PHP function or an array containing a class/Reference and a method to call
     */
    public function getFactory(): string|array|null
    {
        return $this->factory;
    }

    /**
     * Sets the service that this service is decorating.
     *
     * @param string|null $id        The decorated service id, use null to remove decoration
     * @param string|null $renamedId The new decorated service id
     *
     * @return $this
     *
     * @throws InvalidArgumentException in case the decorated service id and the new decorated service id are equals
     */
    public function setDecoratedService(?string $id, string $renamedId = null, int $priority = 0, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): static
    {
        if ($renamedId && $id === $renamedId) {
            throw new InvalidArgumentException(sprintf('The decorated service inner name for "%s" must be different than the service name itself.', $id));
        }

        $this->changes['decorated_service'] = true;

        if (null === $id) {
            $this->decoratedService = null;
        } else {
            $this->decoratedService = [$id, $renamedId, $priority];

            if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $invalidBehavior) {
                $this->decoratedService[] = $invalidBehavior;
            }
        }

        return $this;
    }

    /**
     * Gets the service that this service is decorating.
     *
     * @return array|null An array composed of the decorated service id, the new id for it and the priority of decoration, null if no service is decorated
     */
    public function getDecoratedService(): ?array
    {
        return $this->decoratedService;
    }

    /**
     * Sets the service class.
     *
     * @return $this
     */
    public function setClass(?string $class): static
    {
        $this->changes['class'] = true;

        $this->class = $class;

        return $this;
    }

    /**
     * Gets the service class.
     */
    public function getClass(): ?string
    {
        return $this->class;
    }

    /**
     * Sets the arguments to pass to the service constructor/factory method.
     *
     * @return $this
     */
    public function setArguments(array $arguments): static
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Sets the properties to define when creating the service.
     *
     * @return $this
     */
    public function setProperties(array $properties): static
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * Gets the properties to define when creating the service.
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Sets a specific property.
     *
     * @return $this
     */
    public function setProperty(string $name, mixed $value): static
    {
        $this->properties[$name] = $value;

        return $this;
    }

    /**
     * Adds an argument to pass to the service constructor/factory method.
     *
     * @return $this
     */
    public function addArgument(mixed $argument): static
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * Replaces a specific argument.
     *
     * @return $this
     *
     * @throws OutOfBoundsException When the replaced argument does not exist
     */
    public function replaceArgument(int|string $index, mixed $argument): static
    {
        if (0 === \count($this->arguments)) {
            throw new OutOfBoundsException(sprintf('Cannot replace arguments for class "%s" if none have been configured yet.', $this->class));
        }

        if (\is_int($index) && ($index < 0 || $index > \count($this->arguments) - 1)) {
            throw new OutOfBoundsException(sprintf('The index "%d" is not in the range [0, %d] of the arguments of class "%s".', $index, \count($this->arguments) - 1, $this->class));
        }

        if (!\array_key_exists($index, $this->arguments)) {
            throw new OutOfBoundsException(sprintf('The argument "%s" doesn\'t exist in class "%s".', $index, $this->class));
        }

        $this->arguments[$index] = $argument;

        return $this;
    }

    /**
     * Sets a specific argument.
     *
     * @return $this
     */
    public function setArgument(int|string $key, mixed $value): static
    {
        $this->arguments[$key] = $value;

        return $this;
    }

    /**
     * Gets the arguments to pass to the service constructor/factory method.
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Gets an argument to pass to the service constructor/factory method.
     *
     * @throws OutOfBoundsException When the argument does not exist
     */
    public function getArgument(int|string $index): mixed
    {
        if (!\array_key_exists($index, $this->arguments)) {
            throw new OutOfBoundsException(sprintf('The argument "%s" doesn\'t exist in class "%s".', $index, $this->class));
        }

        return $this->arguments[$index];
    }

    /**
     * Sets the methods to call after service initialization.
     *
     * @return $this
     */
    public function setMethodCalls(array $calls = []): static
    {
        $this->calls = [];
        foreach ($calls as $call) {
            $this->addMethodCall($call[0], $call[1], $call[2] ?? false);
        }

        return $this;
    }

    /**
     * Adds a method to call after service initialization.
     *
     * @param string $method       The method name to call
     * @param array  $arguments    An array of arguments to pass to the method call
     * @param bool   $returnsClone Whether the call returns the service instance or not
     *
     * @return $this
     *
     * @throws InvalidArgumentException on empty $method param
     */
    public function addMethodCall(string $method, array $arguments = [], bool $returnsClone = false): static
    {
        if (empty($method)) {
            throw new InvalidArgumentException('Method name cannot be empty.');
        }
        $this->calls[] = $returnsClone ? [$method, $arguments, true] : [$method, $arguments];

        return $this;
    }

    /**
     * Removes a method to call after service initialization.
     *
     * @return $this
     */
    public function removeMethodCall(string $method): static
    {
        foreach ($this->calls as $i => $call) {
            if ($call[0] === $method) {
                unset($this->calls[$i]);
            }
        }

        return $this;
    }

    /**
     * Check if the current definition has a given method to call after service initialization.
     */
    public function hasMethodCall(string $method): bool
    {
        foreach ($this->calls as $call) {
            if ($call[0] === $method) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the methods to call after service initialization.
     */
    public function getMethodCalls(): array
    {
        return $this->calls;
    }

    /**
     * Sets the definition templates to conditionally apply on the current definition, keyed by parent interface/class.
     *
     * @param ChildDefinition[] $instanceof
     *
     * @return $this
     */
    public function setInstanceofConditionals(array $instanceof): static
    {
        $this->instanceof = $instanceof;

        return $this;
    }

    /**
     * Gets the definition templates to conditionally apply on the current definition, keyed by parent interface/class.
     *
     * @return ChildDefinition[]
     */
    public function getInstanceofConditionals(): array
    {
        return $this->instanceof;
    }

    /**
     * Sets whether or not instanceof conditionals should be prepended with a global set.
     *
     * @return $this
     */
    public function setAutoconfigured(bool $autoconfigured): static
    {
        $this->changes['autoconfigured'] = true;

        $this->autoconfigured = $autoconfigured;

        return $this;
    }

    public function isAutoconfigured(): bool
    {
        return $this->autoconfigured;
    }

    /**
     * Sets tags for this definition.
     *
     * @return $this
     */
    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Returns all tags.
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Gets a tag by name.
     */
    public function getTag(string $name): array
    {
        return $this->tags[$name] ?? [];
    }

    /**
     * Adds a tag for this definition.
     *
     * @return $this
     */
    public function addTag(string $name, array $attributes = []): static
    {
        $this->tags[$name][] = $attributes;

        return $this;
    }

    /**
     * Whether this definition has a tag with the given name.
     */
    public function hasTag(string $name): bool
    {
        return isset($this->tags[$name]);
    }

    /**
     * Clears all tags for a given name.
     *
     * @return $this
     */
    public function clearTag(string $name): static
    {
        unset($this->tags[$name]);

        return $this;
    }

    /**
     * Clears the tags for this definition.
     *
     * @return $this
     */
    public function clearTags(): static
    {
        $this->tags = [];

        return $this;
    }

    /**
     * Sets a file to require before creating the service.
     *
     * @return $this
     */
    public function setFile(?string $file): static
    {
        $this->changes['file'] = true;

        $this->file = $file;

        return $this;
    }

    /**
     * Gets the file to require before creating the service.
     */
    public function getFile(): ?string
    {
        return $this->file;
    }

    /**
     * Sets if the service must be shared or not.
     *
     * @return $this
     */
    public function setShared(bool $shared): static
    {
        $this->changes['shared'] = true;

        $this->shared = $shared;

        return $this;
    }

    /**
     * Whether this service is shared.
     */
    public function isShared(): bool
    {
        return $this->shared;
    }

    /**
     * Sets the visibility of this service.
     *
     * @return $this
     */
    public function setPublic(bool $boolean): static
    {
        $this->changes['public'] = true;

        $this->public = $boolean;

        return $this;
    }

    /**
     * Whether this service is public facing.
     */
    public function isPublic(): bool
    {
        return $this->public;
    }

    /**
     * Whether this service is private.
     */
    public function isPrivate(): bool
    {
        return !$this->public;
    }

    /**
     * Sets the lazy flag of this service.
     *
     * @return $this
     */
    public function setLazy(bool $lazy): static
    {
        $this->changes['lazy'] = true;

        $this->lazy = $lazy;

        return $this;
    }

    /**
     * Whether this service is lazy.
     */
    public function isLazy(): bool
    {
        return $this->lazy;
    }

    /**
     * Sets whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     *
     * @return $this
     */
    public function setSynthetic(bool $boolean): static
    {
        $this->synthetic = $boolean;

        if (!isset($this->changes['public'])) {
            $this->setPublic(true);
        }

        return $this;
    }

    /**
     * Whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     */
    public function isSynthetic(): bool
    {
        return $this->synthetic;
    }

    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     *
     * @return $this
     */
    public function setAbstract(bool $boolean): static
    {
        $this->abstract = $boolean;

        return $this;
    }

    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     */
    public function isAbstract(): bool
    {
        return $this->abstract;
    }

    /**
     * Whether this definition is deprecated, that means it should not be called
     * anymore.
     *
     * @param string $package The name of the composer package that is triggering the deprecation
     * @param string $version The version of the package that introduced the deprecation
     * @param string $message The deprecation message to use
     *
     * @return $this
     *
     * @throws InvalidArgumentException when the message template is invalid
     */
    public function setDeprecated(string $package, string $version, string $message): static
    {
        if ('' !== $message) {
            if (preg_match('#[\r\n]|\*/#', $message)) {
                throw new InvalidArgumentException('Invalid characters found in deprecation template.');
            }

            if (!str_contains($message, '%service_id%')) {
                throw new InvalidArgumentException('The deprecation template must contain the "%service_id%" placeholder.');
            }
        }

        $this->changes['deprecated'] = true;
        $this->deprecation = ['package' => $package, 'version' => $version, 'message' => $message ?: self::DEFAULT_DEPRECATION_TEMPLATE];

        return $this;
    }

    /**
     * Whether this definition is deprecated, that means it should not be called
     * anymore.
     */
    public function isDeprecated(): bool
    {
        return (bool) $this->deprecation;
    }

    /**
     * @param string $id Service id relying on this definition
     */
    public function getDeprecation(string $id): array
    {
        return [
            'package' => $this->deprecation['package'],
            'version' => $this->deprecation['version'],
            'message' => str_replace('%service_id%', $id, $this->deprecation['message']),
        ];
    }

    /**
     * Sets a configurator to call after the service is fully initialized.
     *
     * @param string|array|Reference|null $configurator A PHP function, reference or an array containing a class/Reference and a method to call
     *
     * @return $this
     */
    public function setConfigurator(string|array|Reference|null $configurator): static
    {
        $this->changes['configurator'] = true;

        if (\is_string($configurator) && str_contains($configurator, '::')) {
            $configurator = explode('::', $configurator, 2);
        } elseif ($configurator instanceof Reference) {
            $configurator = [$configurator, '__invoke'];
        }

        $this->configurator = $configurator;

        return $this;
    }

    /**
     * Gets the configurator to call after the service is fully initialized.
     */
    public function getConfigurator(): string|array|null
    {
        return $this->configurator;
    }

    /**
     * Is the definition autowired?
     */
    public function isAutowired(): bool
    {
        return $this->autowired;
    }

    /**
     * Enables/disables autowiring.
     *
     * @return $this
     */
    public function setAutowired(bool $autowired): static
    {
        $this->changes['autowired'] = true;

        $this->autowired = $autowired;

        return $this;
    }

    /**
     * Gets bindings.
     *
     * @return BoundArgument[]
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Sets bindings.
     *
     * Bindings map $named or FQCN arguments to values that should be
     * injected in the matching parameters (of the constructor, of methods
     * called and of controller actions).
     *
     * @return $this
     */
    public function setBindings(array $bindings): static
    {
        foreach ($bindings as $key => $binding) {
            if (0 < strpos($key, '$') && $key !== $k = preg_replace('/[ \t]*\$/', ' $', $key)) {
                unset($bindings[$key]);
                $bindings[$key = $k] = $binding;
            }
            if (!$binding instanceof BoundArgument) {
                $bindings[$key] = new BoundArgument($binding);
            }
        }

        $this->bindings = $bindings;

        return $this;
    }

    /**
     * Add an error that occurred when building this Definition.
     *
     * @return $this
     */
    public function addError(string|\Closure|Definition $error): static
    {
        if ($error instanceof self) {
            $this->errors = array_merge($this->errors, $error->errors);
        } else {
            $this->errors[] = $error;
        }

        return $this;
    }

    /**
     * Returns any errors that occurred while building this Definition.
     */
    public function getErrors(): array
    {
        foreach ($this->errors as $i => $error) {
            if ($error instanceof \Closure) {
                $this->errors[$i] = (string) $error();
            } elseif (!\is_string($error)) {
                $this->errors[$i] = (string) $error;
            }
        }

        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return (bool) $this->errors;
    }
}
