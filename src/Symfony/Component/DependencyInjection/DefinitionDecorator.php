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

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * This definition decorates another definition.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class DefinitionDecorator extends Definition
{
    private $parent;
    private $changes = array();

    /**
     * @param string $parent The id of Definition instance to decorate
     */
    public function __construct($parent)
    {
        parent::__construct();

        $this->parent = $parent;
    }

    /**
     * Returns the Definition being decorated.
     *
     * @return string
     */
    public function getParent()
    {
        return $this->parent;
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
     * {@inheritdoc}
     */
    public function setClass($class)
    {
        $this->changes['class'] = true;

        return parent::setClass($class);
    }

    /**
     * {@inheritdoc}
     */
    public function setFactory($callable)
    {
        $this->changes['factory'] = true;

        return parent::setFactory($callable);
    }

    /**
     * {@inheritdoc}
     */
    public function setConfigurator($callable)
    {
        $this->changes['configurator'] = true;

        return parent::setConfigurator($callable);
    }

    /**
     * {@inheritdoc}
     */
    public function setFile($file)
    {
        $this->changes['file'] = true;

        return parent::setFile($file);
    }

    /**
     * {@inheritdoc}
     */
    public function setPublic($boolean)
    {
        $this->changes['public'] = true;

        return parent::setPublic($boolean);
    }

    /**
     * {@inheritdoc}
     */
    public function setLazy($boolean)
    {
        $this->changes['lazy'] = true;

        return parent::setLazy($boolean);
    }

    /**
     * {@inheritdoc}
     */
    public function setDecoratedService($id, $renamedId = null, $priority = 0)
    {
        $this->changes['decorated_service'] = true;

        return parent::setDecoratedService($id, $renamedId, $priority);
    }

    /**
     * {@inheritdoc}
     */
    public function setDeprecated($boolean = true, $template = null)
    {
        $this->changes['deprecated'] = true;

        return parent::setDeprecated($boolean, $template);
    }

    /**
     * {@inheritdoc}
     */
    public function setAutowired($autowired)
    {
        $this->changes['autowire'] = true;

        return parent::setAutowired($autowired);
    }

    /**
     * Gets an argument to pass to the service constructor/factory method.
     *
     * If replaceArgument() has been used to replace an argument, this method
     * will return the replacement value.
     *
     * @param int $index
     *
     * @return mixed The argument value
     *
     * @throws OutOfBoundsException When the argument does not exist
     */
    public function getArgument($index)
    {
        if (array_key_exists('index_'.$index, $this->arguments)) {
            return $this->arguments['index_'.$index];
        }

        $lastIndex = count(array_filter(array_keys($this->arguments), 'is_int')) - 1;

        if ($index < 0 || $index > $lastIndex) {
            throw new OutOfBoundsException(sprintf('The index "%d" is not in the range [0, %d].', $index, $lastIndex));
        }

        return $this->arguments[$index];
    }

    /**
     * You should always use this method when overwriting existing arguments
     * of the parent definition.
     *
     * If you directly call setArguments() keep in mind that you must follow
     * certain conventions when you want to overwrite the arguments of the
     * parent definition, otherwise your arguments will only be appended.
     *
     * @param int   $index
     * @param mixed $value
     *
     * @return DefinitionDecorator the current instance
     *
     * @throws InvalidArgumentException when $index isn't an integer
     */
    public function replaceArgument($index, $value)
    {
        if (!is_int($index)) {
            throw new InvalidArgumentException('$index must be an integer.');
        }

        $this->arguments['index_'.$index] = $value;

        return $this;
    }

    /**
     * Creates a new Definition by merging the current decorator with the given parent definition.
     *
     * @return Definition
     */
    public function resolveChanges(Definition $parentDef)
    {
        if ($parentDef instanceof self) {
            throw new InvalidArgumentException('$parenfDef must be a resolved Definition.');
        }
        $def = new Definition();

        // merge in parent definition
        // purposely ignored attributes: abstract, tags
        $def->setClass($parentDef->getClass());
        $def->setArguments($parentDef->getArguments());
        $def->setMethodCalls($parentDef->getMethodCalls());
        $def->setProperties($parentDef->getProperties());
        $def->setAutowiringTypes($parentDef->getAutowiringTypes());
        if ($parentDef->isDeprecated()) {
            $def->setDeprecated(true, $parentDef->getDeprecationMessage('%service_id%'));
        }
        $def->setFactory($parentDef->getFactory());
        $def->setConfigurator($parentDef->getConfigurator());
        $def->setFile($parentDef->getFile());
        $def->setPublic($parentDef->isPublic());
        $def->setLazy($parentDef->isLazy());
        $def->setAutowired($parentDef->isAutowired());

        // overwrite with values specified in the decorator
        $changes = $this->getChanges();
        if (isset($changes['class'])) {
            $def->setClass($this->getClass());
        }
        if (isset($changes['factory'])) {
            $def->setFactory($this->getFactory());
        }
        if (isset($changes['configurator'])) {
            $def->setConfigurator($this->getConfigurator());
        }
        if (isset($changes['file'])) {
            $def->setFile($this->getFile());
        }
        if (isset($changes['public'])) {
            $def->setPublic($this->isPublic());
        }
        if (isset($changes['lazy'])) {
            $def->setLazy($this->isLazy());
        }
        if (isset($changes['deprecated'])) {
            $def->setDeprecated($this->isDeprecated(), $this->getDeprecationMessage('%service_id%'));
        }
        if (isset($changes['autowire'])) {
            $def->setAutowired($this->isAutowired());
        }
        if (isset($changes['decorated_service'])) {
            $decoratedService = $this->getDecoratedService();
            if (null === $decoratedService) {
                $def->setDecoratedService($decoratedService);
            } else {
                $def->setDecoratedService($decoratedService[0], $decoratedService[1], $decoratedService[2]);
            }
        }

        // merge arguments
        foreach ($this->getArguments() as $k => $v) {
            if (is_numeric($k)) {
                $def->addArgument($v);
                continue;
            }

            if (0 !== strpos($k, 'index_')) {
                throw new RuntimeException(sprintf('Invalid argument key "%s" found.', $k));
            }

            $index = (int) substr($k, strlen('index_'));
            $def->replaceArgument($index, $v);
        }

        // merge properties
        foreach ($this->getProperties() as $k => $v) {
            $def->setProperty($k, $v);
        }

        // append method calls
        if ($calls = $this->getMethodCalls()) {
            $def->setMethodCalls(array_merge($def->getMethodCalls(), $calls));
        }

        // merge autowiring types
        foreach ($this->getAutowiringTypes() as $autowiringType) {
            $def->addAutowiringType($autowiringType);
        }

        // these attributes are always taken from the child
        $def->setAbstract($this->isAbstract());
        $def->setShared($this->isShared());
        $def->setTags($this->getTags());

        return $def;
    }
}
