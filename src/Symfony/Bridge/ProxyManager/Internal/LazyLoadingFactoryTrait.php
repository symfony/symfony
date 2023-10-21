<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\Internal;

use ProxyManager\Configuration;

/**
 * @internal
 */
trait LazyLoadingFactoryTrait
{
    private readonly ProxyGenerator $generator;

    public function __construct(Configuration $config, ProxyGenerator $generator)
    {
        parent::__construct($config);
        $this->generator = $generator;
    }

    public function getGenerator(): ProxyGenerator
    {
        return $this->generator;
    }
}
