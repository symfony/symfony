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
 * A service id can contain lowercased letters, digits, underscores, and dots.
 * Underscores are used to separate words, and dots to group services
 * under namespaces:
 *
 * <ul>
 *   <li>request</li>
 *   <li>mysql_session_storage</li>
 *   <li>symfony.mysql_session_storage</li>
 * </ul>
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
    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    protected $services = array();
    protected $methodMap = array();
    protected $privates = array();
    protected $aliases = array();
    protected $loading = array();

    private $underscoreMap = array('_' => '', '.' => '_', '\\' => '_');
    private $envCache = array();

    /**
     * @param ParameterBagInterface $parameterBag A ParameterBagInterface instance
     */
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
     * @param string $id      The service identifier
     * @param object $service The service instance
     */
    public function set($id, $service)
    {
        $id = strtolower($id);

        if ('service_container' === $id) {
            throw new InvalidArgumentException('You cannot set service "service_container".');
        }

        if (isset($this->aliases[$id])) {
            unset($this->aliases[$id]);
        }

        $this->services[$id] = $service;

        if (null === $service) {
            unset($this->services[$id]);
        }

        if (isset($this->privates[$id])) {
            if (null === $service) {
                @trigger_error(sprintf('Unsetting the "%s" private service is deprecated since Symfony 3.2 and won\'t be supported anymore in Symfony 4.0.', $id), E_USER_DEPRECATED);
                unset($this->privates[$id]);
            } else {
                @trigger_error(sprintf('Setting the "%s" private service is deprecated since Symfony 3.2 and won\'t be supported anymore in Symfony 4.0. A new public service will be created instead.', $id), E_USER_DEPRECATED);
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
            if ('service_container' === $id
                || isset($this->aliases[$id])
                || isset($this->services[$id])
            ) {
                return true;
            }

            if (isset($this->privates[$id])) {
                @trigger_error(sprintf('Checking for the existence of the "%s" private service is deprecated since Symfony 3.2 and won\'t be supported anymore in Symfony 4.0.', $id), E_USER_DEPRECATED);
            }

            if (isset($this->methodMap[$id])) {
                return true;
            }

            if (--$i && $id !== $lcId = strtolower($id)) {
                $id = $lcId;
                continue;
            }

            // We only check the convention-based factory in a compiled container (i.e. a child class other than a ContainerBuilder,
            // and only when the dumper has not generated the method map (otherwise the method map is considered to be fully populated by the dumper)
            if (!$this->methodMap && !$this instanceof ContainerBuilder && __CLASS__ !== static::class && method_exists($this, 'get'.strtr($id, $this->underscoreMap).'Service')) {
                @trigger_error('Generating a dumped container without populating the method map is deprecated since 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.', E_USER_DEPRECATED);

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
        // calling strtolower() unless necessary.
        for ($i = 2;;) {
            if ('service_container' === $id) {
                return $this;
            }
            if (isset($this->aliases[$id])) {
                $id = $this->aliases[$id];
            }
            // Re-use shared service instance if it exists.
            if (isset($this->services[$id])) {
                return $this->services[$id];
            }

            if (isset($this->loading[$id])) {
                throw new ServiceCircularReferenceException($id, array_keys($this->loading));
            }

            if (isset($this->methodMap[$id])) {
                $method = $this->methodMap[$id];
            } elseif (--$i && $id !== $lcId = strtolower($id)) {
                $id = $lcId;
                continue;
            } elseif (!$this->methodMap && !$this instanceof ContainerBuilder && __CLASS__ !== static::class && method_exists($this, $method = 'get'.strtr($id, $this->underscoreMap).'Service')) {
                // We only check the convention-based factory in a compiled container (i.e. a child class other than a ContainerBuilder,
                // and only when the dumper has not generated the method map (otherwise the method map is considered to be fully populated by the dumper)
                @trigger_error('Generating a dumped container without populating the method map is deprecated since 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.', E_USER_DEPRECATED);
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
            if (isset($this->privates[$id])) {
                @trigger_error(sprintf('Requesting the "%s" private service is deprecated since Symfony 3.2 and won\'t be supported anymore in Symfony 4.0.', $id), E_USER_DEPRECATED);
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
        $id = strtolower($id);

        if ('service_container' === $id) {
            return false;
        }

        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
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
            @trigger_error('Generating a dumped container without populating the method map is deprecated since 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.', E_USER_DEPRECATED);

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
     * @param string The name of the environment variable
     *
     * @return scalar The value to use for the provided environment variable name
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
        if (false !== $env = getenv($name)) {
            return $this->envCache[$name] = $env;
        }
        if (!$this->hasParameter("env($name)")) {
            throw new EnvNotFoundException($name);
        }

        return $this->envCache[$name] = $this->getParameter("env($name)");
    }

    private function __clone()
    {
    }
}
