<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle;

use Assetic\FilterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lazy filter manager.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class LazyFilterManager extends FilterManager
{
    protected $container;
    protected $mappings;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container The service container
     * @param array              $mappings  A hash of filter names to service ids
     */
    public function __construct(ContainerInterface $container, array $mappings)
    {
        $this->container = $container;
        $this->mappings = $mappings;
    }

    public function get($name)
    {
        return isset($this->mappings[$name])
            ? $this->container->get($this->mappings[$name])
            : parent::get($name);
    }

    public function has($name)
    {
        return isset($this->mappings) || parent::has($name);
    }

    public function all()
    {
        $filters = array();
        foreach ($this->mappings as $name => $id) {
            $filters[$name] = $this->container->get($id);
        }

        return $filters + parent::all();
    }
}
