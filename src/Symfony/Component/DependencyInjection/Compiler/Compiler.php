<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
    protected $passConfig;
    protected $currentPass;
    protected $currentStartTime;
    protected $log;
    protected $serviceReferenceGraph;

    public function __construct()
    {
        $this->passConfig = new PassConfig();
        $this->serviceReferenceGraph = new ServiceReferenceGraph();
        $this->log = array();
    }

    public function getPassConfig()
    {
        return $this->passConfig;
    }

    public function getServiceReferenceGraph()
    {
        return $this->serviceReferenceGraph;
    }

    public function addPass(CompilerPassInterface $pass, $type = PassConfig::TYPE_BEFORE_OPTIMIZATION)
    {
        $this->passConfig->addPass($pass, $type);
    }

    public function addLogMessage($string)
    {
        $this->log[] = $string;
    }

    public function getLog()
    {
        return $this->log;
    }

    public function compile(ContainerBuilder $container)
    {
        foreach ($this->passConfig->getPasses() as $pass) {
            $this->startPass($pass);
            $pass->process($container);
            $this->endPass($pass);
        }
    }

    protected function startPass(CompilerPassInterface $pass)
    {
        if ($pass instanceof CompilerAwareInterface) {
            $pass->setCompiler($this);
        }

        $this->currentPass = $pass;
        $this->currentStartTime = microtime(true);
    }

    protected function endPass(CompilerPassInterface $pass)
    {
        $this->currentPass = null;
        $this->addLogMessage(sprintf('%s finished in %.3fs', get_class($pass), microtime(true) - $this->currentStartTime));
    }
}