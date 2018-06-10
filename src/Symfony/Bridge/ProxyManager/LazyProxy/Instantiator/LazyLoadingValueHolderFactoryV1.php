<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\LazyProxy\Instantiator;

use ProxyManager\Factory\LazyLoadingValueHolderFactory as BaseFactory;
use Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\LazyLoadingValueHolderGenerator;

/**
 * @internal
 */
class LazyLoadingValueHolderFactoryV1 extends BaseFactory
{
    private $generatorV1;

    /**
     * {@inheritdoc}
     */
    protected function getGenerator()
    {
        return $this->generatorV1 ?: $this->generatorV1 = new LazyLoadingValueHolderGenerator();
    }
}
