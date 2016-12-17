<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Service;

/**
 * The kernel service represents a kernel as a service throughout the ecosystem.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class Kernel
{
    private $environment;
    private $debug;
    private $bundles;

    /**
     * Constructor.
     *
     * @param string $environment
     * @param bool   $debug
     * @param array  $bundles
     */
    public function __construct($environment, $debug, array $bundles = array())
    {
        $this->environment = $environment;
        $this->debug = $debug;
        $this->bundles = array();
        $numBundles = count($bundles);
        $numProcessedBundles = 0;
        do {
            foreach ($bundles as $name => $bundle) {
                $parent = $bundle['parent'];
                if (null !== $parent && !isset($this->bundles[$parent])) {
                    continue;
                }
                if (!isset($this->bundles[$name])) {
                    $serviceClass = $bundle['service_class'];
                    $parentBundle = isset($this->bundles[$parent]) ? $this->bundles[$parent] : null;
                    $this->bundles[$name] = new $serviceClass($name, $bundle['namespace'], $bundle['class'], $bundle['path'], $parentBundle);
                    ++$numProcessedBundles;
                }
            }
        } while ($numProcessedBundles < $numBundles);
    }

    final public function getEnvironment()
    {
        return $this->environment;
    }

    final public function isDebug()
    {
        return $this->debug;
    }

    final public function getBundles()
    {
        return $this->bundles;
    }
}
