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
use Doctrine\Common\Annotations\PsrCachedReader;
use Doctrine\Common\Annotations\Reader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

/**
 * Warms up annotation caches for classes found in composer's autoload class map
 * and declared in DI bundle extensions using the addAnnotatedClassesToCache method.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 *
 * @deprecated since Symfony 6.4 without replacement
 */
class AnnotationsCacheWarmer extends AbstractPhpFileCacheWarmer
{
    /**
     * @param string $phpArrayFile The PHP file where annotations are cached
     */
    public function __construct(
        private readonly Reader $annotationReader,
        string $phpArrayFile,
        private readonly ?string $excludeRegexp = null,
        private readonly bool $debug = false,
        /* bool $triggerDeprecation = true, */
    ) {
        if (\func_num_args() < 5 || func_get_arg(4)) {
            trigger_deprecation('symfony/framework-bundle', '6.4', 'The "%s" class is deprecated without replacement.', __CLASS__);
        }

        parent::__construct($phpArrayFile);
    }

    /**
     * @param string|null $buildDir
     */
    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter /* , string $buildDir = null */): bool
    {
        $annotatedClassPatterns = $cacheDir.'/annotations.map';

        if (!is_file($annotatedClassPatterns)) {
            return true;
        }

        $annotatedClasses = include $annotatedClassPatterns;
        $reader = new PsrCachedReader($this->annotationReader, $arrayAdapter, $this->debug);

        foreach ($annotatedClasses as $class) {
            if (null !== $this->excludeRegexp && preg_match($this->excludeRegexp, $class)) {
                continue;
            }
            try {
                $this->readAllComponents($reader, $class);
            } catch (\Exception $e) {
                $this->ignoreAutoloadException($class, $e);
            }
        }

        return true;
    }

    /**
     * @return string[] A list of classes to preload on PHP 7.4+
     */
    protected function warmUpPhpArrayAdapter(PhpArrayAdapter $phpArrayAdapter, array $values): array
    {
        // make sure we don't cache null values
        $values = array_filter($values, fn ($val) => null !== $val);

        return parent::warmUpPhpArrayAdapter($phpArrayAdapter, $values);
    }

    private function readAllComponents(Reader $reader, string $class): void
    {
        $reflectionClass = new \ReflectionClass($class);

        try {
            $reader->getClassAnnotations($reflectionClass);
        } catch (AnnotationException) {
            /*
             * Ignore any AnnotationException to not break the cache warming process if an Annotation is badly
             * configured or could not be found / read / etc.
             *
             * In particular cases, an Annotation in your code can be used and defined only for a specific
             * environment but is always added to the annotations.map file by some Symfony default behaviors,
             * and you always end up with a not found Annotation.
             */
        }

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            try {
                $reader->getMethodAnnotations($reflectionMethod);
            } catch (AnnotationException) {
            }
        }

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            try {
                $reader->getPropertyAnnotations($reflectionProperty);
            } catch (AnnotationException) {
            }
        }
    }
}
