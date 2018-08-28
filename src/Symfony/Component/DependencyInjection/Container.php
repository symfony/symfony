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

use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Container is a dependency injection container.
 *
 * It gives access to object instances (services).
 *
 * Services and parameters are simple key/pair stores.
 *
 * Parameter and service keys are case insensitive.
 *
 * A service can also be defined by creating a method named
 * getXXXService(), where XXX is the camelized version of the id:
 *
 *  * request -> getRequestService()
 *  * mysql_session_storage -> getMysqlSessionStorageService()
 *  * symfony.mysql_session_storage -> getSymfony_MysqlSessionStorageService()
 *
 * The container can have three possible behaviors when a service does not exist:
 *
 *  * EXCEPTION_ON_INVALID_REFERENCE: Throws an exception (the default)
 *  * NULL_ON_INVALID_REFERENCE:      Returns null
 *  * IGNORE_ON_INVALID_REFERENCE:    Ignores the wrapping command asking for the reference
 *                                    (for instance, ignore a setter if the service does not exist)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Container implements IntrospectableContainerInterface, ResettableContainerInterface
{
    protected $parameterBag;
    protected $services = array();
    protected $methodMap = array();
    protected $aliases = array();
    protected $scopes = array();
    protected $scopeChildren = array();
    protected $scopedServices = array();
    protected $scopeStacks = array();
    protected $loading = array();

    private $underscoreMap = array('_' => '', '.' => '_', '\\' => '_');

    public function __construct(ParameterBagInterface $parameterBag = null)
    {
        $this->parameterBag = $parameterBag ?: new ParameterBag();
    }

    /**
     * Compiles the container.
     *
     * This method does two things:
     *
     *  * Parameter values are resolved;
     *  * The parameter bag is frozen.
     */
    public function compile()
    {
        $this->parameterBag->resolve();

        $this->parameterBag = new FrozenParameterBag($this->parameterBag->all());
    }

    /**
     * Returns true if the container parameter bag are frozen.
     *
     * @return bool true if the container parameter bag are frozen, false otherwise
     */
    public function isFrozen()
    {
        return $this->parameterBag instanceof FrozenParameterBag;
    }

    /**
     * Gets the service container parameter bag.
     *
     * @return ParameterBagInterface A ParameterBagInterface instance
     */
    public function getParameterBag()
    {
        return $this->parameterBag;
    }

    /**
     * Gets a parameter.
     *
     * @param string $name The parameter name
     *
     * @return mixed The parameter value
     *
     * @throws InvalidArgumentException if the parameter is not defined
     */
    public function getParameter($name)
    {
        return $this->parameterBag->get($name);
    }

    /**
     * Checks if a parameter exists.
     *
     * @param string $name The parameter name
     *
     * @return bool The presence of parameter in container
     */
    public function hasParameter($name)
    {
        return $this->parameterBag->has($name);
    }

    /**
     * Sets a parameter.
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     */
    public function setParameter($name, $value)
    {
        $this->parameterBag->set($name, $value);
    }

    /**
     * Sets a service.
     *
     * Setting a service to null resets the service: has() returns false and get()
     * behaves in the same way as if the service was never created.
     *
     * Note: The $scope parameter is deprecated since version 2.8 and will be removed in 3.0.
     *
     * @param string $id      The service identifier
     * @param object $service The service instance
     * @param string $scope   The scope of the service
     *
     * @throws RuntimeException         When trying to set a service in an inactive scope
     * @throws InvalidArgumentException When trying to set a service in the prototype scope
     */
    public function set($id, $service, $scope = self::SCOPE_CONTAINER)
    {
        if (!\in_array($scope, array('container', 'request')) || ('request' === $scope && 'request' !== $id)) {
            @trigger_error('The concept of container scopes is deprecated since Symfony 2.8 and will be removed in 3.0. Omit the third parameter.', E_USER_DEPRECATED);
        }

        if (self::SCOPE_PROTOTYPE === $scope) {
            throw new InvalidArgumentException(sprintf('You cannot set service "%s" of scope "prototype".', $id));
        }

        $id = strtolower($id);

        if ('service_container' === $id) {
            // BC: 'service_container' is no longer a self-reference but always
            // $this, so ignore this call.
            // @todo Throw InvalidArgumentException in next major release.
            return;
        }
        if (self::SCOPE_CONTAINER !== $scope) {
            if (!isset($this->scopedServices[$scope])) {
                throw new RuntimeException(sprintf('You cannot set service "%s" of inactive scope.', $id));
            }

            $this->scopedServices[$scope][$id] = $service;
        }

        if (isset($this->aliases[$id])) {
            unset($this->aliases[$id]);
        }

        $this->services[$id] = $service;

        if (method_exists($this, $method = 'synchronize'.strtr($id, $this->underscoreMap).'Service')) {
            $this->$method();
        }

        if (null === $service) {
            if (self::SCOPE_CONTAINER !== $scope) {
                unset($this->scopedServices[$scope][$id]);
            }

            unset($this->services[$id]);
        }
    }

    /**
     * Returns true if the given service is defined.
     *
     * @param string $id The service identifier
     *
     * @return bool true if the service is defined, false otherwise
     */
    public function has($id)
    {
        for ($i = 2;;) {
            if ('service_container' === $id
                || isset($this->aliases[$id])
                || isset($this->services[$id])
                || array_key_exists($id, $this->services)
            ) {
                return true;
            }
            if (--$i && $id !== $lcId = strtolower($id)) {
                $id = $lcId;
            } else {
                return method_exists($this, 'get'.strtr($id, $this->underscoreMap).'Service');
            }
        }
    }

    /**
     * Gets a service.
     *
     * If a service is defined both through a set() method and
     * with a get{$id}Service() method, the former has always precedence.
     *
     * @param string $id              The service identifier
     * @param int    $invalidBehavior The behavior when the service does not exist
     *
     * @return object The associated service
     *
     * @throws ServiceCircularReferenceException When a circular reference is detected
     * @throws ServiceNotFoundException          When the service is not defined
     * @throws \Exception                        if an exception has been thrown when the service has been resolved
     *
     * @see Reference
     */
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE)
    {
        // Attempt to retrieve the service by checking first aliases then
        // available services. Service IDs are case insensitive, however since
        // this method can be called thousands of times during a request, avoid
        // calling strtolower() unless necessary.
        for ($i = 2;;) {
            if (isset($this->aliases[$id])) {
                $id = $this->aliases[$id];
            }
            // Re-use shared service instance if it exists.
            if (isset($this->services[$id]) || array_key_exists($id, $this->services)) {
                return $this->services[$id];
            }
            if ('service_container' === $id) {
                return $this;
            }

            if (isset($this->loading[$id])) {
                throw new ServiceCircularReferenceException($id, array_keys($this->loading));
            }

            if (isset($this->methodMap[$id])) {
                $method = $this->methodMap[$id];
            } elseif (--$i && $id !== $lcId = strtolower($id)) {
                $id = $lcId;
                continue;
            } elseif (method_exists($this, $method = 'get'.strtr($id, $this->underscoreMap).'Service')) {
                // $method is set to the right value, proceed
            } else {
                if (self::EXCEPTION_ON_INVALID_REFERENCE === $invalidBehavior) {
                    if (!$id) {
                        throw new ServiceNotFoundException($id);
                    }

                    $alternatives = array();
                    foreach ($this->getServiceIds() as $knownId) {
                        $lev = levenshtein($id, $knownId);
                        if ($lev <= \strlen($id) / 3 || false !== strpos($knownId, $id)) {
                            $alternatives[] = $knownId;
                        }
                    }

                    throw new ServiceNotFoundException($id, null, null, $alternatives);
                }

                return;
            }

            $this->loading[$id] = true;

            try {
                $service = $this->$method();
            } catch (\Exception $e) {
                unset($this->loading[$id]);
                unset($this->services[$id]);

                if ($e instanceof InactiveScopeException && self::EXCEPTION_ON_INVALID_REFERENCE !== $invalidBehavior) {
                    return;
                }

                throw $e;
            } catch (\Throwable $e) {
                unset($this->loading[$id]);
                unset($this->services[$id]);

                throw $e;
            }

            unset($this->loading[$id]);

            return $service;
        }
    }

    /**
     * Returns true if the given service has actually been initialized.
     *
     * @param string $id The service identifier
     *
     * @return bool true if service has already been initialized, false otherwise
     */
    public function initialized($id)
    {
        $id = strtolower($id);

        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        if ('service_container' === $id) {
            // BC: 'service_container' was a synthetic service previously.
            // @todo Change to false in next major release.
            return true;
        }

        return isset($this->services[$id]) || array_key_exists($id, $this->services);
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        if (!empty($this->scopedServices)) {
            throw new LogicException('Resetting the container is not allowed when a scope is active.');
        }

        $this->services = array();
    }

    /**
     * Gets all service ids.
     *
     * @return array An array of all defined service ids
     */
    public function getServiceIds()
    {
        $ids = array();
        foreach (get_class_methods($this) as $method) {
            if (preg_match('/^get(.+)Service$/', $method, $match)) {
                $ids[] = self::underscore($match[1]);
            }
        }
        $ids[] = 'service_container';

        return array_unique(array_merge($ids, array_keys($this->services)));
    }

    /**
     * This is called when you enter a scope.
     *
     * @param string $name
     *
     * @throws RuntimeException         When the parent scope is inactive
     * @throws InvalidArgumentException When the scope does not exist
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function enterScope($name)
    {
        if ('request' !== $name) {
            @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);
        }

        if (!isset($this->scopes[$name])) {
            throw new InvalidArgumentException(sprintf('The scope "%s" does not exist.', $name));
        }

        if (self::SCOPE_CONTAINER !== $this->scopes[$name] && !isset($this->scopedServices[$this->scopes[$name]])) {
            throw new RuntimeException(sprintf('The parent scope "%s" must be active when entering this scope.', $this->scopes[$name]));
        }

        // check if a scope of this name is already active, if so we need to
        // remove all services of this scope, and those of any of its child
        // scopes from the global services map
        if (isset($this->scopedServices[$name])) {
            $services = array($this->services, $name => $this->scopedServices[$name]);
            unset($this->scopedServices[$name]);

            foreach ($this->scopeChildren[$name] as $child) {
                if (isset($this->scopedServices[$child])) {
                    $services[$child] = $this->scopedServices[$child];
                    unset($this->scopedServices[$child]);
                }
            }

            // update global map
            $this->services = \call_user_func_array('array_diff_key', $services);
            array_shift($services);

            // add stack entry for this scope so we can restore the removed services later
            if (!isset($this->scopeStacks[$name])) {
                $this->scopeStacks[$name] = new \SplStack();
            }
            $this->scopeStacks[$name]->push($services);
        }

        $this->scopedServices[$name] = array();
    }

    /**
     * This is called to leave the current scope, and move back to the parent
     * scope.
     *
     * @param string $name The name of the scope to leave
     *
     * @throws InvalidArgumentException if the scope is not active
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function leaveScope($name)
    {
        if ('request' !== $name) {
            @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);
        }

        if (!isset($this->scopedServices[$name])) {
            throw new InvalidArgumentException(sprintf('The scope "%s" is not active.', $name));
        }

        // remove all services of this scope, or any of its child scopes from
        // the global service map
        $services = array($this->services, $this->scopedServices[$name]);
        unset($this->scopedServices[$name]);

        foreach ($this->scopeChildren[$name] as $child) {
            if (isset($this->scopedServices[$child])) {
                $services[] = $this->scopedServices[$child];
                unset($this->scopedServices[$child]);
            }
        }

        // update global map
        $this->services = \call_user_func_array('array_diff_key', $services);

        // check if we need to restore services of a previous scope of this type
        if (isset($this->scopeStacks[$name]) && \count($this->scopeStacks[$name]) > 0) {
            $services = $this->scopeStacks[$name]->pop();
            $this->scopedServices += $services;

            if ($this->scopeStacks[$name]->isEmpty()) {
                unset($this->scopeStacks[$name]);
            }

            foreach ($services as $array) {
                foreach ($array as $id => $service) {
                    $this->set($id, $service, $name);
                }
            }
        }
    }

    /**
     * Adds a scope to the container.
     *
     * @throws InvalidArgumentException
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function addScope(ScopeInterface $scope)
    {
        $name = $scope->getName();
        $parentScope = $scope->getParentName();

        if ('request' !== $name) {
            @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);
        }
        if (self::SCOPE_CONTAINER === $name || self::SCOPE_PROTOTYPE === $name) {
            throw new InvalidArgumentException(sprintf('The scope "%s" is reserved.', $name));
        }
        if (isset($this->scopes[$name])) {
            throw new InvalidArgumentException(sprintf('A scope with name "%s" already exists.', $name));
        }
        if (self::SCOPE_CONTAINER !== $parentScope && !isset($this->scopes[$parentScope])) {
            throw new InvalidArgumentException(sprintf('The parent scope "%s" does not exist, or is invalid.', $parentScope));
        }

        $this->scopes[$name] = $parentScope;
        $this->scopeChildren[$name] = array();

        // normalize the child relations
        while (self::SCOPE_CONTAINER !== $parentScope) {
            $this->scopeChildren[$parentScope][] = $name;
            $parentScope = $this->scopes[$parentScope];
        }
    }

    /**
     * Returns whether this container has a certain scope.
     *
     * @param string $name The name of the scope
     *
     * @return bool
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function hasScope($name)
    {
        if ('request' !== $name) {
            @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);
        }

        return isset($this->scopes[$name]);
    }

    /**
     * Returns whether this scope is currently active.
     *
     * This does not actually check if the passed scope actually exists.
     *
     * @param string $name
     *
     * @return bool
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function isScopeActive($name)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

        return isset($this->scopedServices[$name]);
    }

    /**
     * Camelizes a string.
     *
     * @param string $id A string to camelize
     *
     * @return string The camelized string
     */
    public static function camelize($id)
    {
        return strtr(ucwords(strtr($id, array('_' => ' ', '.' => '_ ', '\\' => '_ '))), array(' ' => ''));
    }

    /**
     * A string to underscore.
     *
     * @param string $id The string to underscore
     *
     * @return string The underscored string
     */
    public static function underscore($id)
    {
        return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), str_replace('_', '.', $id)));
    }

    private function __clone()
    {
    }
}
