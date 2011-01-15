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

/**
 * A pass that might be run repeatedly.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RepeatedPass implements CompilerPassInterface, CompilerAwareInterface
{
    protected $repeat;
    protected $compiler;
    protected $passes;

    public function __construct(array $passes)
    {
        foreach ($passes as $pass) {
            if (!$pass instanceof RepeatablePassInterface) {
                throw new \InvalidArgumentException('$passes must be an array of RepeatablePassInterface.');
            }

            $pass->setRepeatedPass($this);
        }

        $this->passes = $passes;
    }

    public function setCompiler(Compiler $compiler)
    {
        $this->compiler = $compiler;
    }

    public function getCompiler()
    {
        return $this->compiler;
    }

    public function process(ContainerBuilder $container)
    {
        $this->repeat = false;
        foreach ($this->passes as $pass) {
            $time = microtime(true);
            $pass->process($container);
            $this->compiler->addLogMessage(sprintf(
                '%s finished in %.3fs', get_class($pass), microtime(true) - $time
            ));
        }

        if ($this->repeat) {
            $this->process($container);
        }
    }

    public function setRepeat()
    {
        $this->repeat = true;
    }
}