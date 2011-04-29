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

/**
 * ApcUniversalClassLoader implements a "universal" autoloader cached in APC for PHP 5.3.
 *
 * It is able to load classes that use either:
 *
 *  * The technical interoperability standards for PHP 5.3 namespaces and
 *    class names (http://groups.google.com/group/php-standards/web/psr-0-final-proposal);
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
 *     require 'vendor/symfony/src/Symfony/Component/ClassLoader/ApcUniversalClassLoader.php';
 *
 *     use Symfony\Component\ClassLoader\ApcUniversalClassLoader;
 *
 *     $loader = new ApcUniversalClassLoader('apc.prefix.');
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
 * In this example, if you try to use a class in the Symfony\Component
 * namespace or one of its children (Symfony\Component\Console for instance),
 * the autoloader will first look for the class under the component/
 * directory, and it will then fallback to the framework/ directory if not
 * found before giving up.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 *
 * @api
 */
class ApcUniversalClassLoader extends UniversalClassLoader
{
    private $prefix;

    /**
     * Constructor.
     *
     * @param string $prefix A prefix to create a namespace in APC
     *
     * @api
     */
    public function __construct($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Finds a file by class name while caching lookups to APC.
     *
     * @param string $class A class name to resolve to file
     *
     * @api
     */
    public function findFile($class)
    {
        if (false === $file = apc_fetch($this->prefix.$class)) {
            apc_store($this->prefix.$class, $file = parent::findFile($class));
        }

        return $file;
    }
}
