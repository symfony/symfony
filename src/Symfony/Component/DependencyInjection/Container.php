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

use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

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
 * <ul>
 *   <li>request -> getRequestService()</li>
 *   <li>mysql_session_storage -> getMysqlSessionStorageService()</li>
 *   <li>symfony.mysql_session_storage -> getSymfony_MysqlSessionStorageService()</li>
 * </ul>
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
class Container implements ResettableContainerInterface
{
    protected $parameterBag;
    protected $services = array();
    protected $methodMap = array();
    protected $aliases = array();
    protected $loading = array();

    /**
     * @internal
     */
    protected $privates = array();

    /**
     * @internal
     */
    protected $normalizedIds = array();

    private $underscoreMap = array('_' => '', '.' => '_', '\\' => '_');
    private $envCache = array();
    private $compiled = false;

    public function __construct(ParameterBagInterface $parameterBag = null)
    {
        $this->parameterBag = $parameterBag ?: new EnvPlaceholderParameterBag();
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

        $this->compiled = true;
    }

    /**
     * Returns true if the container is compiled.
     *
     * @return bool
     */
    public function isCompiled()
    {
        return $this->compiled;
    }

    /**
     * Returns true if the container parameter bag are frozen.
     *
     * @deprecated since version 3.3, to be removed in 4.0.
     *
     * @return bool true if the container parameter bag are frozen, false otherwise
     */
    public function isFrozen()
    {
        @trigger_error(sprintf('The %s() method is deprecated since Symfony 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);

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
     * @param string $id      The service identifier
     * @param object $service The service instance
     */
    public function set($id, $service)
    {
        $id = $this->normalizeId($id);

        if ('service_container' === $id) {
            throw new InvalidArgumentException('You cannot set service "service_container".');
        }

        if (isset($this->aliases[$id])) {
            unset($this->aliases[$id]);
        }

        $wasSet = isset($this->services[$id]);
        $this->services[$id] = $service;

        if (null === $service) {
            unset($this->services[$id]);
        }

        if (isset($this->privates[$id])) {
            if (null === $service) {
                @trigger_error(sprintf('The "%s" service is private, unsetting it is deprecated since Symfony 3.2 and will fail in 4.0.', $id), E_USER_DEPRECATED);
                unset($this->privates[$id]);
            } else {
                @trigger_error(sprintf('The "%s" service is private, replacing it is deprecated since Symfony 3.2 and will fail in 4.0.', $id), E_USER_DEPRECATED);
            }
        } elseif ($wasSet && isset($this->methodMap[$id])) {
            if (null === $service) {
                @trigger_error(sprintf('The "%s" service is already initialized, unsetting it is deprecated since Symfony 3.3 and will fail in 4.0.', $id), E_USER_DEPRECATED);
            } else {
                @trigger_error(sprintf('The "%s" service is already initialized, replacing it is deprecated since Symfony 3.3 and will fail in 4.0.', $id), E_USER_DEPRECATED);
            }
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
            if (isset($this->privates[$id])) {
                @trigger_error(sprintf('The "%s" service is private, checking for its existence is deprecated since Symfony 3.2 and will fail in 4.0.', $id), E_USER_DEPRECATED);
            }
            if (isset($this->aliases[$id])) {
                $id = $this->aliases[$id];
            }
            if (isset($this->services[$id])) {
                return true;
            }
            if ('service_container' === $id) {
                return true;
            }

            if (isset($this->methodMap[$id])) {
                return true;
            }

            if (--$i && $id !== $normalizedId = $this->normalizeId($id)) {
                $id = $normalizedId;
                continue;
            }

            // We only check the convention-based factory in a compiled container (i.e. a child class other than a ContainerBuilder,
            // and only when the dumper has not generated the method map (otherwise the method map is considered to be fully populated by the dumper)
            if (!$this->methodMap && !$this instanceof ContainerBuilder && __CLASS__ !== static::class && method_exists($this, 'get'.strtr($id, $this->underscoreMap).'Service')) {
                @trigger_error('Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.', E_USER_DEPRECATED);

                return true;
            }

            return false;
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
        // calling $this->normalizeId($id) unless necessary.
        for ($i = 2;;) {
            if (isset($this->privates[$id])) {
                @trigger_error(sprintf('The "%s" service is private, getting it from the container is deprecated since Symfony 3.2 and will fail in 4.0. You should either make the service public, or stop getting services directly from the container and use dependency injection instead.', $id), E_USER_DEPRECATED);
            }
            if (isset($this->aliases[$id])) {
                $id = $this->aliases[$id];
            }

            // Re-use shared service instance if it exists.
            if (isset($this->services[$id])) {
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
            } elseif (--$i && $id !== $normalizedId = $this->normalizeId($id)) {
                $id = $normalizedId;
                continue;
            } elseif (!$this->methodMap && !$this instanceof ContainerBuilder && __CLASS__ !== static::class && method_exists($this, $method = 'get'.strtr($id, $this->underscoreMap).'Service')) {
                // We only check the convention-based factory in a compiled container (i.e. a child class other than a ContainerBuilder,
                // and only when the dumper has not generated the method map (otherwise the method map is considered to be fully populated by the dumper)
                @trigger_error('Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.', E_USER_DEPRECATED);
                // $method is set to the right value, proceed
            } else {
                if (self::EXCEPTION_ON_INVALID_REFERENCE === $invalidBehavior) {
                    if (!$id) {
                        throw new ServiceNotFoundException($id);
                    }

                    $alternatives = array();
                    foreach ($this->getServiceIds() as $knownId) {
                        $lev = levenshtein($id, $knownId);
                        if ($lev <= strlen($id) / 3 || false !== strpos($knownId, $id)) {
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
                unset($this->services[$id]);

                throw $e;
            } finally {
                unset($this->loading[$id]);
            }

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
        $id = $this->normalizeId($id);

        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        if ('service_container' === $id) {
            return false;
        }

        return isset($this->services[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
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

        if (!$this->methodMap && !$this instanceof ContainerBuilder && __CLASS__ !== static::class) {
            // We only check the convention-based factory in a compiled container (i.e. a child class other than a ContainerBuilder,
            // and only when the dumper has not generated the method map (otherwise the method map is considered to be fully populated by the dumper)
            @trigger_error('Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.', E_USER_DEPRECATED);

            foreach (get_class_methods($this) as $method) {
                if (preg_match('/^get(.+)Service$/', $method, $match)) {
                    $ids[] = self::underscore($match[1]);
                }
            }
        }
        $ids[] = 'service_container';

        return array_unique(array_merge($ids, array_keys($this->methodMap), array_keys($this->services)));
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

    /**
     * Fetches a variable from the environment.
     *
     * @param string $name The name of the environment variable
     *
     * @return mixed The value to use for the provided environment variable name
     *
     * @throws EnvNotFoundException When the environment variable is not found and has no default value
     */
    protected function getEnv($name)
    {
        if (isset($this->envCache[$name]) || array_key_exists($name, $this->envCache)) {
            return $this->envCache[$name];
        }
        if (isset($_ENV[$name])) {
            return $this->envCache[$name] = $_ENV[$name];
        }
        if (isset($_SERVER[$name]) && 0 !== strpos($name, 'HTTP_')) {
            return $this->envCache[$name] = $_SERVER[$name];
        }
        if (false !== ($env = getenv($name)) && null !== $env) { // null is a possible value because of thread safety issues
            return $this->envCache[$name] = $env;
        }
        if (!$this->hasParameter("env($name)")) {
            throw new EnvNotFoundException($name);
        }

        return $this->envCache[$name] = $this->getParameter("env($name)");
    }

    /**
     * Returns the case sensitive id used at registration time.
     *
     * @param string $id
     *
     * @return string
     *
     * @internal
     */
    public function normalizeId($id)
    {
        if (!is_string($id)) {
            $id = (string) $id;
        }
        if (isset($this->normalizedIds[$normalizedId = strtolower($id)])) {
            $normalizedId = $this->normalizedIds[$normalizedId];
            if ($id !== $normalizedId) {
                @trigger_error(sprintf('Service identifiers will be made case sensitive in Symfony 4.0. Using "%s" instead of "%s" is deprecated since Symfony 3.3.', $id, $normalizedId), E_USER_DEPRECATED);
            }
        } else {
            $normalizedId = $this->normalizedIds[$normalizedId] = $id;
        }

        return $normalizedId;
    }

    private function __clone()
    {
    }
}
