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
use Symfony\Component\DependencyInjection\Exception\EnvParameterException;

/**
 * This class is used to remove circular dependencies between individual passes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Compiler
{
    private $passConfig;
    private $log = array();
    private $loggingFormatter;
    private $serviceReferenceGraph;

    public function __construct()
    {
        $this->passConfig = new PassConfig();
        $this->serviceReferenceGraph = new ServiceReferenceGraph();
        $this->loggingFormatter = new LoggingFormatter();
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
     * Returns the logging formatter which can be used by compilation passes.
     *
     * @return LoggingFormatter
     */
    public function getLoggingFormatter()
    {
        return $this->loggingFormatter;
    }

    /**
     * Adds a pass to the PassConfig.
     *
     * @param CompilerPassInterface $pass     A compiler pass
     * @param string                $type     The type of the pass
     * @param int                   $priority Used to sort the passes
     */
    public function addPass(CompilerPassInterface $pass, $type = PassConfig::TYPE_BEFORE_OPTIMIZATION/*, $priority = 0*/)
    {
        if (func_num_args() >= 3) {
            $priority = func_get_arg(2);
        } else {
            if (__CLASS__ !== get_class($this)) {
                $r = new \ReflectionMethod($this, __FUNCTION__);
                if (__CLASS__ !== $r->getDeclaringClass()->getName()) {
                    @trigger_error(sprintf('Method %s() will have a third `$priority = 0` argument in version 4.0. Not defining it is deprecated since 3.2.', __METHOD__), E_USER_DEPRECATED);
                }
            }

            $priority = 0;
        }

        $this->passConfig->addPass($pass, $type, $priority);
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
        try {
            foreach ($this->passConfig->getPasses() as $pass) {
                $pass->process($container);
            }
        } catch (\Exception $e) {
            $usedEnvs = array();
            $prev = $e;

            do {
                $msg = $prev->getMessage();

                if ($msg !== $resolvedMsg = $container->resolveEnvPlaceholders($msg, null, $usedEnvs)) {
                    $r = new \ReflectionProperty($prev, 'message');
                    $r->setAccessible(true);
                    $r->setValue($prev, $resolvedMsg);
                }
            } while ($prev = $prev->getPrevious());

            if ($usedEnvs) {
                $e = new EnvParameterException($usedEnvs, $e);
            }

            throw $e;
        }
    }
}
