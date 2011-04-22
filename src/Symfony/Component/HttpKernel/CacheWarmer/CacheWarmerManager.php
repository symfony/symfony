<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheWarmer;

/**
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheWarmerManager implements CacheWarmerInterface
{
    private $warmers;
    private $optionalsEnabled;

    public function __construct(array $warmers = array())
    {
        $this->setWarmers($warmers);
        $this->optionalsEnabled = false;
    }

    public function enableOptionalWarmers()
    {
        $this->optionalsEnabled = true;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        $warmers = $this->warmers;
        $tree = array();
        
        // Keep only the relevant warmers
        foreach ($this->warmers as $name => $warmer) {
            if (!$this->optionalsEnabled && $warmer->isOptional()) {
                unset($warmers[$name]);
            } else {
                $tree[$name] = array();
            }
        }
        
        // Build the dependency tree
        foreach ($warmers as $name => $warmer) {
            foreach ($warmer->getPreWarmers() as $depName) {
                if (isset($warmers[$depName])) {
                    $tree[$name][] = $depName;
                }
            }
            foreach ($warmer->getPostWarmers() as $reverseDepName) {
                if (isset($warmers[$reverseDepName])) {
                    $tree[$reverseDepName][] = $name;
                }
            }            
        }
        
        // Warm-up
        while (false !== reset($tree)) {
            foreach ($this->resolveDependencies($tree, key($tree), new \ArrayObject(), new \ArrayObject()) as $warmerName) {
                $warmers[$warmerName]->warmUp($cacheDir);
                unset($tree[$warmerName]);                    
            }
        }
    }
    
    /**
     * Checks whether this warmer is optional or not.
     *
     * @return Boolean always true
     */
    public function isOptional()
    {
        return false;
    }

    public function setWarmers(array $warmers)
    {
        $this->warmers = array();
        foreach ($warmers as $warmer) {
            $this->add($warmer);
        }
    }

    /**
     * Adds a cache warmer.
     * 
     * @param CacheWarmerInterface $warmer 
     * 
     * @throws \RuntimeException if a cache warmer with the same name already exists
     */
    public function add(CacheWarmerInterface $warmer)
    {
        $name = $warmer->getName();
        if (array_key_exists($name, $this->warmers)) {
            throw new \RuntimeException(sprintf('Duplicate cache warmer "%s"', $name));
        }
        $this->warmers[$name] = $warmer;
    }
    
    /**
     * Returns the warmer name.
     * 
     * @return string The warmer name
     */
    public function getName() 
    {
        return '_manager';
    }
    
    /**
     * Returns the list or warmers that should be executed before this one.
     * 
     * @return array List of warmers to run before this one
     */    
    public function getPreWarmers() 
    {
        return array();
    }    

    /**
     * Returns the list or warmers that should be executed after this one.
     * 
     * @return array List of warmers to run after this one
     */    
    public function getPostWarmers()
    {
        return array();
    }        
    
    /**
     * Resolve the dependencies for a cache warmer
     * 
     * @param array         $tree       The dependency tree
     * @param string        $node       The node name
     * @param \ArrayObject  $resolved   An array of already resolved dependencies
     * @param \ArrayObject  $unresolved An array of dependencies to be resolved
     * 
     * @return \ArrayObject The dependencies for the given node
     * 
     * @throws \RuntimeException if a circular dependency is detected 
     */
    private function resolveDependencies(array $tree, $node, \ArrayObject $resolved, \ArrayObject $unresolved) 
    {
        if (array_key_exists($node, $tree)) {
            $unresolved[$node] = $node;
            foreach ($tree[$node] as $dependency) {
                if (!$resolved->offsetExists($dependency)) {
                    if ($unresolved->offsetExists($dependency)) {
                        throw new \RuntimeException(sprintf('Circular dependency "%s" - "%s"', $node, $dependency));
                    }
                    $this->resolveDependencies($tree, $dependency, $resolved, $unresolved);
                }
            }
            $resolved[$node] = $node;
            unset($unresolved[$node]);            
        }
        return $resolved;
    }    
}
