<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * UniversalClassLoader implements a "universal" autoloader for PHP 5.3.
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
 *     $loader = new UniversalClassLoader();
 *
 *     // register classes with namespaces
 *     $loader->registerNamespaces(array(
 *       'Symfony\Component' => __DIR__.'/component',
 *       'Symfony' => __DIR__.'/framework',
 *     ));
 *
 *     // register a library using the PEAR naming convention
 *     $loader->registerPrefixes(array(
 *       'Swift_' => __DIR__.'/Swift',
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
 * @author Fabien Potencier <fabien.potencier@symfony-project.org>
 */
class UniversalClassLoader
{
    protected $namespaces = array();
    protected $prefixes = array();
    protected $namespaceFallback;
    protected $prefixFallback;

    /**
     * Gets the configured namespaces.
     *
     * @return array A hash with namespaces as keys and directories as values
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

    /**
     * Gets the configured class prefixes.
     *
     * @return array A hash with class prefixes as keys and directories as values
     */
    public function getPrefixes()
    {
        return $this->prefixes;
    }

    /**
     * Gets the directory to use as a fallback for namespaces.
     *
     * @return string A directory path
     */
    public function getNamespaceFallback()
    {
        return $this->namespaceFallback;
    }

    /**
     * Gets the directory to use as a fallback for class prefixes.
     *
     * @return string A directory path
     */
    public function getPrefixFallback()
    {
        return $this->prefixFallback;
    }

    /**
     * Registers the directory to use as a fallback for namespaces.
     *
     * @return string $dir A directory path
     */
    public function registerNamespaceFallback($dir)
    {
        $this->namespaceFallback = $dir;
    }

    /**
     * Registers the directory to use as a fallback for class prefixes.
     *
     * @param string $dir A directory path
     */
    public function registerPrefixFallback($dir)
    {
        $this->prefixFallback = $dir;
    }

    /**
     * Registers an array of namespaces
     *
     * @param array $namespaces An array of namespaces (namespaces as keys and locations as values)
     */
    public function registerNamespaces(array $namespaces)
    {
        $this->namespaces = array_merge($this->namespaces, $namespaces);
    }

    /**
     * Registers a namespace.
     *
     * @param string $namespace The namespace
     * @param string $path      The location of the namespace
     */
    public function registerNamespace($namespace, $path)
    {
        $this->namespaces[$namespace] = $path;
    }

    /**
     * Registers an array of classes using the PEAR naming convention.
     *
     * @param array $classes An array of classes (prefixes as keys and locations as values)
     */
    public function registerPrefixes(array $classes)
    {
        $this->prefixes = array_merge($this->prefixes, $classes);
    }

    /**
     * Registers a set of classes using the PEAR naming convention.
     *
     * @param string $prefix The classes prefix
     * @param string $path   The location of the classes
     */
    public function registerPrefix($prefix, $path)
    {
        $this->prefixes[$prefix] = $path;
    }

    /**
     * Registers this instance as an autoloader.
     */
    public function register()
    {
        spl_autoload_register(array($this, 'loadClass'));
    }

    /**
     * Loads the given class or interface.
     *
     * @param string $class The name of the class
     */
    public function loadClass($class)
    {
        if ('\\' === $class[0]) {
            $class = substr($class, 1);
        }

        if (false !== ($pos = strripos($class, '\\'))) {
            // namespaced class name
            $namespace = substr($class, 0, $pos);
            foreach ($this->namespaces as $ns => $dir) {
                if (0 === strpos($namespace, $ns)) {
                    $className = substr($class, $pos + 1);
                    $file = $dir.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';
                    if (file_exists($file)) {
                        require $file;
                        return;
                    }
                }
            }

            if (null !== $this->namespaceFallback) {
                $file = $this->namespaceFallback.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
                if (file_exists($file)) {
                    require $file;
                }
            }
        } else {
            // PEAR-like class name
            foreach ($this->prefixes as $prefix => $dir) {
                if (0 === strpos($class, $prefix)) {
                    $file = $dir.DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
                    if (file_exists($file)) {
                        require $file;
                        return;
                    }
                }
            }

            if (null !== $this->prefixFallback) {
                $file = $this->prefixFallback.DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
                if (file_exists($file)) {
                    require $file;
                }
            }
        }
    }
}
