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

    private $class;
    private $file;
    private $factory;
    private $shared = true;
    private $deprecated = false;
    private $deprecationTemplate;
    private $properties = [];
    private $calls = [];
    private $instanceof = [];
    private $autoconfigured = false;
    private $configurator;
    private $tags = [];
    private $public = true;
    private $private = true;
    private $synthetic = false;
    private $abstract = false;
    private $lazy = false;
    private $decoratedService;
    private $autowired = false;
    private $changes = [];
    private $bindings = [];
    private $errors = [];

    protected $arguments = [];

    /**
     * @internal
     *
     * Used to store the name of the inner id when using service decoration together with autowiring
     */
    public $innerServiceId;

    /**
     * @internal
     *
     * Used to store the behavior to follow when using service decoration and the decorated service is invalid
     */
    public $decorationOnInvalid;

    /**
     * @param string|null $class     The service class
     * @param array       $arguments An array of arguments to pass to the service constructor
     */
    public function __construct($class = null, array $arguments = [])
    {
        if (null !== $class) {
            $this->setClass($class);
        }
        $this->arguments = $arguments;
    }

    /**
     * Returns all changes tracked for the Definition object.
     *
     * @return array An array of changes for this Definition
     */
    public function getChanges()
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
    public function setChanges(array $changes)
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
    public function setFactory($factory)
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
    public function getFactory()
    {
        return $this->factory;
    }

    /**
     * Sets the service that this service is decorating.
     *
     * @param string|null $id              The decorated service id, use null to remove decoration
     * @param string|null $renamedId       The new decorated service id
     * @param int         $priority        The priority of decoration
     * @param int         $invalidBehavior The behavior to adopt when decorated is invalid
     *
     * @return $this
     *
     * @throws InvalidArgumentException in case the decorated service id and the new decorated service id are equals
     */
    public function setDecoratedService($id, $renamedId = null, $priority = 0/*, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE*/)
    {
        if ($renamedId && $id === $renamedId) {
            throw new InvalidArgumentException(sprintf('The decorated service inner name for "%s" must be different than the service name itself.', $id));
        }

        $invalidBehavior = 3 < \func_num_args() ? (int) func_get_arg(3) : ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;

        $this->changes['decorated_service'] = true;

        if (null === $id) {
            $this->decoratedService = null;
        } else {
            $this->decoratedService = [$id, $renamedId, (int) $priority];

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
    public function getDecoratedService()
    {
        return $this->decoratedService;
    }

    /**
     * Sets the service class.
     *
     * @param string $class The service class
     *
     * @return $this
     */
    public function setClass($class)
    {
        if ($class instanceof Parameter) {
            @trigger_error(sprintf('Passing an instance of %s as class name to %s in deprecated in Symfony 4.4 and will result in a TypeError in 5.0. Please pass the string "%%%s%%" instead.', Parameter::class, __CLASS__, (string) $class), \E_USER_DEPRECATED);
        }
        if (null !== $class && !\is_string($class)) {
            @trigger_error(sprintf('The class name passed to %s is expected to be a string. Passing a %s is deprecated in Symfony 4.4 and will result in a TypeError in 5.0.', __CLASS__, \is_object($class) ? \get_class($class) : \gettype($class)), \E_USER_DEPRECATED);
        }

        $this->changes['class'] = true;

        $this->class = $class;

        return $this;
    }

    /**
     * Gets the service class.
     *
     * @return string|null The service class
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Sets the arguments to pass to the service constructor/factory method.
     *
     * @return $this
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Sets the properties to define when creating the service.
     *
     * @return $this
     */
    public function setProperties(array $properties)
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * Gets the properties to define when creating the service.
     *
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Sets a specific property.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function setProperty($name, $value)
    {
        $this->properties[$name] = $value;

        return $this;
    }

    /**
     * Adds an argument to pass to the service constructor/factory method.
     *
     * @param mixed $argument An argument
     *
     * @return $this
     */
    public function addArgument($argument)
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * Replaces a specific argument.
     *
     * @param int|string $index
     * @param mixed      $argument
     *
     * @return $this
     *
     * @throws OutOfBoundsException When the replaced argument does not exist
     */
    public function replaceArgument($index, $argument)
    {
        if (0 === \count($this->arguments)) {
            throw new OutOfBoundsException('Cannot replace arguments if none have been configured yet.');
        }

        if (\is_int($index) && ($index < 0 || $index > \count($this->arguments) - 1)) {
            throw new OutOfBoundsException(sprintf('The index "%d" is not in the range [0, %d].', $index, \count($this->arguments) - 1));
        }

        if (!\array_key_exists($index, $this->arguments)) {
            throw new OutOfBoundsException(sprintf('The argument "%s" doesn\'t exist.', $index));
        }

        $this->arguments[$index] = $argument;

        return $this;
    }

    /**
     * Sets a specific argument.
     *
     * @param int|string $key
     * @param mixed      $value
     *
     * @return $this
     */
    public function setArgument($key, $value)
    {
        $this->arguments[$key] = $value;

        return $this;
    }

    /**
     * Gets the arguments to pass to the service constructor/factory method.
     *
     * @return array The array of arguments
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Gets an argument to pass to the service constructor/factory method.
     *
     * @param int|string $index
     *
     * @return mixed The argument value
     *
     * @throws OutOfBoundsException When the argument does not exist
     */
    public function getArgument($index)
    {
        if (!\array_key_exists($index, $this->arguments)) {
            throw new OutOfBoundsException(sprintf('The argument "%s" doesn\'t exist.', $index));
        }

        return $this->arguments[$index];
    }

    /**
     * Sets the methods to call after service initialization.
     *
     * @return $this
     */
    public function setMethodCalls(array $calls = [])
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
    public function addMethodCall($method, array $arguments = []/*, bool $returnsClone = false*/)
    {
        if (empty($method)) {
            throw new InvalidArgumentException('Method name cannot be empty.');
        }
        $this->calls[] = 2 < \func_num_args() && func_get_arg(2) ? [$method, $arguments, true] : [$method, $arguments];

        return $this;
    }

    /**
     * Removes a method to call after service initialization.
     *
     * @param string $method The method name to remove
     *
     * @return $this
     */
    public function removeMethodCall($method)
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
     *
     * @param string $method The method name to search for
     *
     * @return bool
     */
    public function hasMethodCall($method)
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
     *
     * @return array An array of method calls
     */
    public function getMethodCalls()
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
    public function setInstanceofConditionals(array $instanceof)
    {
        $this->instanceof = $instanceof;

        return $this;
    }

    /**
     * Gets the definition templates to conditionally apply on the current definition, keyed by parent interface/class.
     *
     * @return ChildDefinition[]
     */
    public function getInstanceofConditionals()
    {
        return $this->instanceof;
    }

    /**
     * Sets whether or not instanceof conditionals should be prepended with a global set.
     *
     * @param bool $autoconfigured
     *
     * @return $this
     */
    public function setAutoconfigured($autoconfigured)
    {
        $this->changes['autoconfigured'] = true;

        $this->autoconfigured = $autoconfigured;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAutoconfigured()
    {
        return $this->autoconfigured;
    }

    /**
     * Sets tags for this definition.
     *
     * @return $this
     */
    public function setTags(array $tags)
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Returns all tags.
     *
     * @return array An array of tags
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Gets a tag by name.
     *
     * @param string $name The tag name
     *
     * @return array An array of attributes
     */
    public function getTag($name)
    {
        return $this->tags[$name] ?? [];
    }

    /**
     * Adds a tag for this definition.
     *
     * @param string $name       The tag name
     * @param array  $attributes An array of attributes
     *
     * @return $this
     */
    public function addTag($name, array $attributes = [])
    {
        $this->tags[$name][] = $attributes;

        return $this;
    }

    /**
     * Whether this definition has a tag with the given name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasTag($name)
    {
        return isset($this->tags[$name]);
    }

    /**
     * Clears all tags for a given name.
     *
     * @param string $name The tag name
     *
     * @return $this
     */
    public function clearTag($name)
    {
        unset($this->tags[$name]);

        return $this;
    }

    /**
     * Clears the tags for this definition.
     *
     * @return $this
     */
    public function clearTags()
    {
        $this->tags = [];

        return $this;
    }

    /**
     * Sets a file to require before creating the service.
     *
     * @param string $file A full pathname to include
     *
     * @return $this
     */
    public function setFile($file)
    {
        $this->changes['file'] = true;

        $this->file = $file;

        return $this;
    }

    /**
     * Gets the file to require before creating the service.
     *
     * @return string|null The full pathname to include
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Sets if the service must be shared or not.
     *
     * @param bool $shared Whether the service must be shared or not
     *
     * @return $this
     */
    public function setShared($shared)
    {
        $this->changes['shared'] = true;

        $this->shared = (bool) $shared;

        return $this;
    }

    /**
     * Whether this service is shared.
     *
     * @return bool
     */
    public function isShared()
    {
        return $this->shared;
    }

    /**
     * Sets the visibility of this service.
     *
     * @param bool $boolean
     *
     * @return $this
     */
    public function setPublic($boolean)
    {
        $this->changes['public'] = true;

        $this->public = (bool) $boolean;
        $this->private = false;

        return $this;
    }

    /**
     * Whether this service is public facing.
     *
     * @return bool
     */
    public function isPublic()
    {
        return $this->public;
    }

    /**
     * Sets if this service is private.
     *
     * When set, the "private" state has a higher precedence than "public".
     * In version 3.4, a "private" service always remains publicly accessible,
     * but triggers a deprecation notice when accessed from the container,
     * so that the service can be made really private in 4.0.
     *
     * @param bool $boolean
     *
     * @return $this
     */
    public function setPrivate($boolean)
    {
        $this->private = (bool) $boolean;

        return $this;
    }

    /**
     * Whether this service is private.
     *
     * @return bool
     */
    public function isPrivate()
    {
        return $this->private;
    }

    /**
     * Sets the lazy flag of this service.
     *
     * @param bool $lazy
     *
     * @return $this
     */
    public function setLazy($lazy)
    {
        $this->changes['lazy'] = true;

        $this->lazy = (bool) $lazy;

        return $this;
    }

    /**
     * Whether this service is lazy.
     *
     * @return bool
     */
    public function isLazy()
    {
        return $this->lazy;
    }

    /**
     * Sets whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     *
     * @param bool $boolean
     *
     * @return $this
     */
    public function setSynthetic($boolean)
    {
        $this->synthetic = (bool) $boolean;

        return $this;
    }

    /**
     * Whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     *
     * @return bool
     */
    public function isSynthetic()
    {
        return $this->synthetic;
    }

    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     *
     * @param bool $boolean
     *
     * @return $this
     */
    public function setAbstract($boolean)
    {
        $this->abstract = (bool) $boolean;

        return $this;
    }

    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     *
     * @return bool
     */
    public function isAbstract()
    {
        return $this->abstract;
    }

    /**
     * Whether this definition is deprecated, that means it should not be called
     * anymore.
     *
     * @param bool   $status
     * @param string $template Template message to use if the definition is deprecated
     *
     * @return $this
     *
     * @throws InvalidArgumentException when the message template is invalid
     */
    public function setDeprecated($status = true, $template = null)
    {
        if (null !== $template) {
            if (preg_match('#[\r\n]|\*/#', $template)) {
                throw new InvalidArgumentException('Invalid characters found in deprecation template.');
            }

            if (!str_contains($template, '%service_id%')) {
                throw new InvalidArgumentException('The deprecation template must contain the "%service_id%" placeholder.');
            }

            $this->deprecationTemplate = $template;
        }

        $this->changes['deprecated'] = true;

        $this->deprecated = (bool) $status;

        return $this;
    }

    /**
     * Whether this definition is deprecated, that means it should not be called
     * anymore.
     *
     * @return bool
     */
    public function isDeprecated()
    {
        return $this->deprecated;
    }

    /**
     * Message to use if this definition is deprecated.
     *
     * @param string $id Service id relying on this definition
     *
     * @return string
     */
    public function getDeprecationMessage($id)
    {
        return str_replace('%service_id%', $id, $this->deprecationTemplate ?: self::DEFAULT_DEPRECATION_TEMPLATE);
    }

    /**
     * Sets a configurator to call after the service is fully initialized.
     *
     * @param string|array|Reference|null $configurator A PHP function, reference or an array containing a class/Reference and a method to call
     *
     * @return $this
     */
    public function setConfigurator($configurator)
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
     *
     * @return callable|array|null
     */
    public function getConfigurator()
    {
        return $this->configurator;
    }

    /**
     * Is the definition autowired?
     *
     * @return bool
     */
    public function isAutowired()
    {
        return $this->autowired;
    }

    /**
     * Enables/disables autowiring.
     *
     * @param bool $autowired
     *
     * @return $this
     */
    public function setAutowired($autowired)
    {
        $this->changes['autowired'] = true;

        $this->autowired = (bool) $autowired;

        return $this;
    }

    /**
     * Gets bindings.
     *
     * @return array|BoundArgument[]
     */
    public function getBindings()
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
    public function setBindings(array $bindings)
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
     * @param string|\Closure|self $error
     *
     * @return $this
     */
    public function addError($error)
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
     *
     * @return array
     */
    public function getErrors()
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
