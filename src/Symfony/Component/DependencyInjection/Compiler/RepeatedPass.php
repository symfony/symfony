<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A pass that might be run repeatedly.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RepeatedPass implements CompilerPassInterface
{
    protected $repeat;
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

    public function process(ContainerBuilder $container)
    {
        $this->repeat = false;
        foreach ($this->passes as $pass) {
            $pass->process($container);
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