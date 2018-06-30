<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

use Symfony\Component\DependencyInjection\ServiceLocator as BaseServiceLocator;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class ServiceLocator extends BaseServiceLocator
{
    private $factory;
    private $serviceMap;

    public function __construct(\Closure $factory, array $serviceMap)
    {
        $this->factory = $factory;
        $this->serviceMap = $serviceMap;
        parent::__construct($serviceMap);
    }

    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        return isset($this->serviceMap[$id]) ? ($this->factory)(...$this->serviceMap[$id]) : parent::get($id);
    }
}
