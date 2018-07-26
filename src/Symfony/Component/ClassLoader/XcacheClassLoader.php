<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ClassLoader;

@trigger_error('The '.__NAMESPACE__.'\XcacheClassLoader class is deprecated since Symfony 3.3 and will be removed in 4.0. Use `composer install --apcu-autoloader` instead.', E_USER_DEPRECATED);

/**
 * XcacheClassLoader implements a wrapping autoloader cached in XCache for PHP 5.3.
 *
 * It expects an object implementing a findFile method to find the file. This
 * allows using it as a wrapper around the other loaders of the component (the
 * ClassLoader for instance) but also around any other autoloaders following
 * this convention (the Composer one for instance).
 *
 *     // with a Symfony autoloader
 *     $loader = new ClassLoader();
 *     $loader->addPrefix('Symfony\Component', __DIR__.'/component');
 *     $loader->addPrefix('Symfony',           __DIR__.'/framework');
 *
 *     // or with a Composer autoloader
 *     use Composer\Autoload\ClassLoader;
 *
 *     $loader = new ClassLoader();
 *     $loader->add('Symfony\Component', __DIR__.'/component');
 *     $loader->add('Symfony',           __DIR__.'/framework');
 *
 *     $cachedLoader = new XcacheClassLoader('my_prefix', $loader);
 *
 *     // activate the cached autoloader
 *     $cachedLoader->register();
 *
 *     // eventually deactivate the non-cached loader if it was registered previously
 *     // to be sure to use the cached one.
 *     $loader->unregister();
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Kris Wallsmith <kris@symfony.com>
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 *
 * @deprecated since version 3.3, to be removed in 4.0. Use `composer install --apcu-autoloader` instead.
 */
class XcacheClassLoader
{
    private $prefix;
    private $decorated;

    /**
     * @param string $prefix    The XCache namespace prefix to use
     * @param object $decorated A class loader object that implements the findFile() method
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __construct($prefix, $decorated)
    {
        if (!\extension_loaded('xcache')) {
            throw new \RuntimeException('Unable to use XcacheClassLoader as XCache is not enabled.');
        }

        if (!method_exists($decorated, 'findFile')) {
            throw new \InvalidArgumentException('The class finder must implement a "findFile" method.');
        }

        $this->prefix = $prefix;
        $this->decorated = $decorated;
    }

    /**
     * Registers this instance as an autoloader.
     *
     * @param bool $prepend Whether to prepend the autoloader or not
     */
    public function register($prepend = false)
    {
        spl_autoload_register(array($this, 'loadClass'), true, $prepend);
    }

    /**
     * Unregisters this instance as an autoloader.
     */
    public function unregister()
    {
        spl_autoload_unregister(array($this, 'loadClass'));
    }

    /**
     * Loads the given class or interface.
     *
     * @param string $class The name of the class
     *
     * @return bool|null True, if loaded
     */
    public function loadClass($class)
    {
        if ($file = $this->findFile($class)) {
            require $file;

            return true;
        }
    }

    /**
     * Finds a file by class name while caching lookups to Xcache.
     *
     * @param string $class A class name to resolve to file
     *
     * @return string|null
     */
    public function findFile($class)
    {
        if (xcache_isset($this->prefix.$class)) {
            $file = xcache_get($this->prefix.$class);
        } else {
            $file = $this->decorated->findFile($class) ?: null;
            xcache_set($this->prefix.$class, $file);
        }

        return $file;
    }

    /**
     * Passes through all unknown calls onto the decorated object.
     */
    public function __call($method, $args)
    {
        return \call_user_func_array(array($this->decorated, $method), $args);
    }
}
