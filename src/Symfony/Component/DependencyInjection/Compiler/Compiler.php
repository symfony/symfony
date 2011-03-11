<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

/**
 * This class is used to remove circular dependencies between individual passes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Compiler
{
    private $passConfig;
    private $currentPass;
    private $currentStartTime;
    private $log;
    private $serviceReferenceGraph;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->passConfig = new PassConfig();
        $this->serviceReferenceGraph = new ServiceReferenceGraph();
        $this->log = array();
    }

    /**
     * Returns the PassConfig.
     *
     * @return PassConfig The PassConfig instance
     */
    public function getPassConfig()
    {
        return $this->passConfig;
    }

    /**
     * Returns the ServiceReferenceGraph.
     *
     * @return ServiceReferenceGraph The ServiceReferenceGraph instance
     */
    public function getServiceReferenceGraph()
    {
        return $this->serviceReferenceGraph;
    }

    /**
     * Adds a pass to the PassConfig.
     *
     * @param CompilerPassInterface $pass A compiler pass
     * @param string $type The type of the pass
     */
    public function addPass(CompilerPassInterface $pass, $type = PassConfig::TYPE_BEFORE_OPTIMIZATION)
    {
        $this->passConfig->addPass($pass, $type);
    }

    /**
     * Adds a log message.
     *
     * @param string $string The log message 
     */
    public function addLogMessage($string)
    {
        $this->log[] = $string;
    }

    /**
     * Returns the log.
     *
     * @return array Log array
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Run the Compiler and process all Passes.
     *
     * @param ContainerBuilder $container 
     */
    public function compile(ContainerBuilder $container)
    {
        foreach ($this->passConfig->getPasses() as $pass) {
            $this->startPass($pass);
            $pass->process($container);
            $this->endPass($pass);
        }
    }

    /**
     * Starts an individual pass.
     *
     * @param CompilerPassInterface $pass The pass to start
     */
    private function startPass(CompilerPassInterface $pass)
    {
        $this->currentPass = $pass;
        $this->currentStartTime = microtime(true);
    }

    /**
     * Ends an individual pass.
     *
     * @param CompilerPassInterface $pass The compiler pass
     */
    private function endPass(CompilerPassInterface $pass)
    {
        $this->currentPass = null;
        $this->addLogMessage(sprintf('%s finished in %.3fs', get_class($pass), microtime(true) - $this->currentStartTime));
    }
}