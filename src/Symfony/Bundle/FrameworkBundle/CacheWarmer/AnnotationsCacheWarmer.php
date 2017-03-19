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
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\DoctrineProvider;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Warms up annotation caches for classes found in composer's autoload class map
 * and declared in DI bundle extensions using the addAnnotatedClassesToCache method.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class AnnotationsCacheWarmer implements CacheWarmerInterface
{
    private $annotationReader;
    private $phpArrayFile;
    private $fallbackPool;

    /**
     * @param Reader                 $annotationReader
     * @param string                 $phpArrayFile     The PHP file where annotations are cached
     * @param CacheItemPoolInterface $fallbackPool     The pool where runtime-discovered annotations are cached
     */
    public function __construct(Reader $annotationReader, $phpArrayFile, CacheItemPoolInterface $fallbackPool)
    {
        $this->annotationReader = $annotationReader;
        $this->phpArrayFile = $phpArrayFile;
        if (!$fallbackPool instanceof AdapterInterface) {
            $fallbackPool = new ProxyAdapter($fallbackPool);
        }
        $this->fallbackPool = $fallbackPool;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        $adapter = new PhpArrayAdapter($this->phpArrayFile, $this->fallbackPool);
        $annotatedClassPatterns = $cacheDir.'/annotations.map';

        if (!is_file($annotatedClassPatterns)) {
            $adapter->warmUp(array());

            return;
        }

        $annotatedClasses = include $annotatedClassPatterns;

        $arrayPool = new ArrayAdapter(0, false);
        $reader = new CachedReader($this->annotationReader, new DoctrineProvider($arrayPool));

        spl_autoload_register(array($adapter, 'throwOnRequiredClass'));
        try {
            foreach ($annotatedClasses as $class) {
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
        } finally {
            spl_autoload_unregister(array($adapter, 'throwOnRequiredClass'));
        }

        $values = $arrayPool->getValues();
        $adapter->warmUp($values);

        foreach ($values as $k => $v) {
            $item = $this->fallbackPool->getItem($k);
            $this->fallbackPool->saveDeferred($item->set($v));
        }
        $this->fallbackPool->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function isOptional()
    {
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
