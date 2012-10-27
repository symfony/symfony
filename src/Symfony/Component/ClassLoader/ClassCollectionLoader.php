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
 * ClassCollectionLoader.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassCollectionLoader
{
    private static $loaded;
    private static $seen;
    private static $useTokenizer = true;

    /**
     * Loads a list of classes and caches them in one big file.
     *
     * @param array   $classes    An array of classes to load
     * @param string  $cacheDir   A cache directory
     * @param string  $name       The cache name prefix
     * @param Boolean $autoReload Whether to flush the cache when the cache is stale or not
     * @param Boolean $adaptive   Whether to remove already declared classes or not
     * @param string  $extension  File extension of the resulting file
     *
     * @throws \InvalidArgumentException When class can't be loaded
     */
    public static function load($classes, $cacheDir, $name, $autoReload, $adaptive = false, $extension = '.php')
    {
        // each $name can only be loaded once per PHP process
        if (isset(self::$loaded[$name])) {
            return;
        }

        self::$loaded[$name] = true;

        $declared = array_merge(get_declared_classes(), get_declared_interfaces());
        if (function_exists('get_declared_traits')) {
            $declared = array_merge($declared, get_declared_traits());
        }

        if ($adaptive) {
            // don't include already declared classes
            $classes = array_diff($classes, $declared);

            // the cache is different depending on which classes are already declared
            $name = $name.'-'.substr(md5(implode('|', $classes)), 0, 5);
        }

        $classes = array_unique($classes);

        $cache = $cacheDir.'/'.$name.$extension;

        // auto-reload
        $reload = false;
        if ($autoReload) {
            $metadata = $cacheDir.'/'.$name.$extension.'.meta';
            if (!is_file($metadata) || !is_file($cache)) {
                $reload = true;
            } else {
                $time = filemtime($cache);
                $meta = unserialize(file_get_contents($metadata));

                sort($meta[1]);
                sort($classes);

                if ($meta[1] != $classes) {
                    $reload = true;
                } else {
                    foreach ($meta[0] as $resource) {
                        if (!is_file($resource) || filemtime($resource) > $time) {
                            $reload = true;

                            break;
                        }
                    }
                }
            }
        }

        if (!$reload && is_file($cache)) {
            require_once $cache;

            return;
        }

        $files = array();
        $content = '';
        foreach (self::getOrderedClasses($classes) as $class) {
            if (in_array($class->getName(), $declared)) {
                continue;
            }

            $files[] = $class->getFileName();

            $c = preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($class->getFileName()));

            // add namespace declaration for global code
            if (!$class->inNamespace()) {
                $c = "\nnamespace\n{\n".self::stripComments($c)."\n}\n";
            } else {
                $c = self::fixNamespaceDeclarations('<?php '.$c);
                $c = preg_replace('/^\s*<\?php/', '', $c);
            }

            $content .= $c;
        }

        // cache the core classes
        if (!is_dir(dirname($cache))) {
            mkdir(dirname($cache), 0777, true);
        }
        self::writeCacheFile($cache, '<?php '.$content);

        if ($autoReload) {
            // save the resources
            self::writeCacheFile($metadata, serialize(array($files, $classes)));
        }
    }

    /**
     * Adds brackets around each namespace if it's not already the case.
     *
     * @param string $source Namespace string
     *
     * @return string Namespaces with brackets
     */
    public static function fixNamespaceDeclarations($source)
    {
        if (!function_exists('token_get_all') || !self::$useTokenizer) {
            if (preg_match('/namespace(.*?)\s*;/', $source)) {
                $source = preg_replace('/namespace(.*?)\s*;/', "namespace$1\n{", $source)."}\n";
            }

            return $source;
        }

        $output = '';
        $inNamespace = false;
        $tokens = token_get_all($source);

        for ($i = 0, $max = count($tokens); $i < $max; $i++) {
            $token = $tokens[$i];
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                // strip comments
                continue;
            } elseif (T_NAMESPACE === $token[0]) {
                if ($inNamespace) {
                    $output .= "}\n";
                }
                $output .= $token[1];

                // namespace name and whitespaces
                while (($t = $tokens[++$i]) && is_array($t) && in_array($t[0], array(T_WHITESPACE, T_NS_SEPARATOR, T_STRING))) {
                    $output .= $t[1];
                }
                if (is_string($t) && '{' === $t) {
                    $inNamespace = false;
                    --$i;
                } else {
                    $output = rtrim($output);
                    $output .= "\n{";
                    $inNamespace = true;
                }
            } else {
                $output .= $token[1];
            }
        }

        if ($inNamespace) {
            $output .= "}\n";
        }

        return $output;
    }

    /**
     * Writes a cache file.
     *
     * @param string $file    Filename
     * @param string $content Temporary file content
     *
     * @throws \RuntimeException when a cache file cannot be written
     */
    private static function writeCacheFile($file, $content)
    {
        $tmpFile = tempnam(dirname($file), basename($file));
        if (false !== @file_put_contents($tmpFile, $content) && @rename($tmpFile, $file)) {
            @chmod($file, 0666 & ~umask());

            return;
        }

        throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file));
    }

    /**
     * Removes comments from a PHP source string.
     *
     * We don't use the PHP php_strip_whitespace() function
     * as we want the content to be readable and well-formatted.
     *
     * @param string $source A PHP string
     *
     * @return string The PHP string with the comments removed
     */
    private static function stripComments($source)
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (!in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                $output .= $token[1];
            }
        }

        // replace multiple new lines with a single newline
        $output = preg_replace(array('/\s+$/Sm', '/\n+/S'), "\n", $output);

        return $output;
    }

    /**
     * Gets an ordered array of passed classes including all their dependencies.
     *
     * @param array $classes
     *
     * @return array An array of sorted \ReflectionClass instances (dependencies added if needed)
     *
     * @throws \InvalidArgumentException When a class can't be loaded
     */
    private static function getOrderedClasses(array $classes)
    {
        $map = array();
        self::$seen = array();
        foreach ($classes as $class) {
            try {
                $reflectionClass = new \ReflectionClass($class);
            } catch (\ReflectionException $e) {
                throw new \InvalidArgumentException(sprintf('Unable to load class "%s"', $class));
            }

            $map = array_merge($map, self::getClassHierarchy($reflectionClass));
        }

        return $map;
    }

    private static function getClassHierarchy(\ReflectionClass $class)
    {
        if (isset(self::$seen[$class->getName()])) {
            return array();
        }

        self::$seen[$class->getName()] = true;

        $classes = array($class);
        $parent = $class;
        while (($parent = $parent->getParentClass()) && $parent->isUserDefined() && !isset(self::$seen[$parent->getName()])) {
            self::$seen[$parent->getName()] = true;

            array_unshift($classes, $parent);
        }

        if (function_exists('get_declared_traits')) {
            foreach ($classes as $c) {
                foreach (self::getTraits($c) as $trait) {
                    self::$seen[$trait->getName()] = true;

                    array_unshift($classes, $trait);
                }
            }
        }

        return array_merge(self::getInterfaces($class), $classes);
    }

    private static function getInterfaces(\ReflectionClass $class)
    {
        $classes = array();

        foreach ($class->getInterfaces() as $interface) {
            $classes = array_merge($classes, self::getInterfaces($interface));
        }

        if ($class->isUserDefined() && $class->isInterface() && !isset(self::$seen[$class->getName()])) {
            self::$seen[$class->getName()] = true;

            $classes[] = $class;
        }

        return $classes;
    }

    private static function getTraits(\ReflectionClass $class)
    {
        $traits = $class->getTraits();
        $classes = array();
        while ($trait = array_pop($traits)) {
            if ($trait->isUserDefined() && !isset(self::$seen[$trait->getName()])) {
                $classes[] = $trait;

                $traits = array_merge($traits, $trait->getTraits());
            }
        }

        return $classes;
    }

    /**
     * This method is only useful for testing.
     */
    public static function enableTokenizer($bool)
    {
        self::$useTokenizer = (Boolean) $bool;
    }
}
