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

use Symfony\Component\ClassLoader\UniversalClassLoader;
/**
 * XcacheUniversalClassLoader implements a "universal" autoloader cached in Xcache for PHP 5.3.
 *
 * It is able to load classes that use either:
 *
 *  * The technical interoperability standards for PHP 5.3 namespaces and
 *    class names (https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md);
 *
 *  * The PEAR naming convention for classes (http://pear.php.net/).
 *
 * Classes from a sub-namespace or a sub-hierarchy of PEAR classes can be
 * looked for in a list of locations to ease the vendoring of a sub-set of
 * classes for large projects.
 *
 * Example usage:
 *
 *     require 'vendor/symfony/src/Symfony/Component/ClassLoader/UniversalClassLoader.php';
 *     require 'vendor/symfony/src/Symfony/Component/ClassLoader/XcacheUniversalClassLoader.php';
 *
 *     use Symfony\Component\ClassLoader\XcacheUniversalClassLoader;
 *
 *     $loader = new XcacheUniversalClassLoader('xcache.prefix.');
 *
 *     // register classes with namespaces
 *     $loader->registerNamespaces(array(
 *         'Symfony\Component' => __DIR__.'/component',
 *         'Symfony'           => __DIR__.'/framework',
 *         'Sensio'            => array(__DIR__.'/src', __DIR__.'/vendor'),
 *     ));
 *
 *     // register a library using the PEAR naming convention
 *     $loader->registerPrefixes(array(
 *         'Swift_' => __DIR__.'/Swift',
 *     ));
 *
 *     // activate the autoloader
 *     $loader->register();
 *
 *
 * @author Mike Lohmann <mike.lohmann@icans-gmbh.com>
 *
 * @api
 */
class XcacheUniversalClassLoader extends UniversalClassLoader
{
    private $prefix;

    /**
     * Constructor.
     *
     * @param string $prefix A prefix to create a namespace in Xcache
     *
     * @api
     */
    public function __construct($prefix = 'xcache.prefix.')
    {
        if (!extension_loaded('xcache')) {
            throw new \RuntimeException('Unable to use XcacheUniversalClassLoader as Xcache is not enabled.');
        }

        $this->prefix = $prefix;
    }

    /**
     * Finds a file by class name while caching lookups to Xcache.
     *
     * @param string $class A class name to resolve to file
     */
    public function findFile($class)
    {
        /*
         * Due to an know unfeature of xcache the context have to be checked to be transparent for
         * command-line commands (like build-scripts)
         * @see: http://xcache.lighttpd.net/ticket/228
         */
        if(PHP_SAPI != 'cli'){
            if (null === $file = xcache_get($this->prefix.$class)) {
                $file = parent::findFile($class);
                xcache_set($this->prefix.$class, $file);
            }
        } else {
            $file = parent::findFile($class);
        }

        return $file;
    }

    /**
     * Will return the prefix set
     *
     * @return string $prefix
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

}
