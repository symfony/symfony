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
     * @param string                 $phpArrayFile     The PHP file where annotations are cached.
     * @param CacheItemPoolInterface $fallbackPool     The pool where runtime-discovered annotations are cached.
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

        foreach ($annotatedClasses as $class) {
            $this->readAllComponents($reader, $class);
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
