<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;

/**
 * Warms up annotation caches for classes found in composer's autoload class map
 * and declared in DI bundle extensions using the addAnnotatedClassesToCache method.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class AnnotationsCacheWarmer extends AbstractPhpFileCacheWarmer
{
    private $annotationReader;
    private $excludeRegexp;
    private $debug;

    /**
     * @param string $phpArrayFile  The PHP file where annotations are cached
     * @param string $excludeRegexp
     * @param bool   $debug
     */
    public function __construct(Reader $annotationReader, string $phpArrayFile, $excludeRegexp = null, $debug = false)
    {
        if ($excludeRegexp instanceof CacheItemPoolInterface) {
            @trigger_error(sprintf('The CacheItemPoolInterface $fallbackPool argument of "%s()" is deprecated since Symfony 4.2, you should not pass it anymore.', __METHOD__), E_USER_DEPRECATED);
            $excludeRegexp = $debug;
            $debug = 4 < \func_num_args() && func_get_arg(4);
        }
        parent::__construct($phpArrayFile);
        $this->annotationReader = $annotationReader;
        $this->excludeRegexp = $excludeRegexp;
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    protected function doWarmUp($cacheDir, ArrayAdapter $arrayAdapter)
    {
        $annotatedClassPatterns = $cacheDir.'/annotations.map';

        if (!is_file($annotatedClassPatterns)) {
            return true;
        }

        $annotatedClasses = include $annotatedClassPatterns;
        $reader = new CachedReader($this->annotationReader, new DoctrineProvider($arrayAdapter), $this->debug);

        foreach ($annotatedClasses as $class) {
            if (null !== $this->excludeRegexp && preg_match($this->excludeRegexp, $class)) {
                continue;
            }
            try {
                $this->readAllComponents($reader, $class);
            } catch (\ReflectionException $e) {
                // ignore failing reflection
            } catch (AnnotationException $e) {
                /*
                 * Ignore any AnnotationException to not break the cache warming process if an Annotation is badly
                 * configured or could not be found / read / etc.
                 *
                 * In particular cases, an Annotation in your code can be used and defined only for a specific
                 * environment but is always added to the annotations.map file by some Symfony default behaviors,
                 * and you always end up with a not found Annotation.
                 */
            }
        }

        return true;
    }

    private function readAllComponents(Reader $reader, $class)
    {
        $reflectionClass = new \ReflectionClass($class);
        $reader->getClassAnnotations($reflectionClass);

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $reader->getMethodAnnotations($reflectionMethod);
        }

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $reader->getPropertyAnnotations($reflectionProperty);
        }
    }
}
