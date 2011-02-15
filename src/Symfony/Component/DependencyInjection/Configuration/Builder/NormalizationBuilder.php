<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

/**
 * This class builds normalization conditions.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class NormalizationBuilder
{
    public $parent;
    public $before;
    public $remappings;

    /**
     * Constructor
     *
     * @param Symfony\Component\DependencyInjection\Configuration\Builder\NodeBuilder $parent
     */
    public function __construct($parent)
    {
        $this->parent = $parent;

        $this->keys = false;

        $this->remappings =
        $this->before =
        $this->after = array();
    }

    /**
     * Registers a key to remap to its plural form.
     *
     * @param string $key    The key to remap
     * @param string $plural The plural of the key in case of irregular plural
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\NormalizationBuilder
     */
    public function remap($key, $plural = null)
    {
        if (null === $plural) {
            $plural = $key.'s';
        }

        $this->remappings[] = array($key, $plural);

        return $this;
    }

    /**
     * Registers a closure to run before the normalization or an expression builder to build it if null is provided.
     *
     * @param \Closure $closure
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder|Symfony\Component\DependencyInjection\Configuration\Builder\NormalizationBuilder
     */
    public function before(\Closure $closure = null)
    {
        if (null !== $closure) {
            $this->before[] = $closure;

            return $this;
        }

        return $this->before[] = new ExprBuilder($this->parent);
    }
}