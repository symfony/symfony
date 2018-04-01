<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\ProxyManager\LazyProxy\Instantiator;

use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use ProxyManager\Factory\LazyLoadingValueHolderFactory as BaseFactory;
use Symphony\Bridge\ProxyManager\LazyProxy\PhpDumper\LazyLoadingValueHolderGenerator;

/**
 * @internal
 */
class LazyLoadingValueHolderFactoryV2 extends BaseFactory
{
    private $generator;

    /**
     * {@inheritdoc}
     */
    protected function getGenerator(): ProxyGeneratorInterface
    {
        return $this->generator ?: $this->generator = new LazyLoadingValueHolderGenerator();
    }
}
