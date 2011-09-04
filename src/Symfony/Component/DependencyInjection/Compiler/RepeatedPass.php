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

/**
 * A pass that might be run repeatedly.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RepeatedPass implements CompilerPassInterface
{
    private $repeat;
    private $passes;

    /**
     * Constructor.
     *
     * @param array $passes An array of RepeatablePassInterface objects
     */
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

    /**
     * Process the repeatable passes that run more than once.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $compiler = $container->getCompiler();
        $this->repeat = false;
        foreach ($this->passes as $pass) {
            $pass->process($container);
        }

        if ($this->repeat) {
            $this->process($container);
        }
    }

    /**
     * Sets if the pass should repeat
     */
    public function setRepeat()
    {
        $this->repeat = true;
    }

    /**
     * Returns the passes
     *
     * @return array An array of RepeatablePassInterface objects
     */
    public function getPasses()
    {
        return $this->passes;
    }
}
