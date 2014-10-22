<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating;

/**
 * EngineAwareTrait should be implemented by classes that depends on a template engine.
 *
 * @author Vincent Blanchon <blanchon.vincent@gmail.com>
 */
trait EngineAwareTrait
{
    /**
     * @var EngineInterface
     */
    protected $engine;

    /**
     * Sets the Engine
     *
     * @param EngineInterface $engine A EngineInterface instance
     */
    public function setEngine(EngineInterface $engine = null)
    {
        $this->engine = $engine;
    }
}
