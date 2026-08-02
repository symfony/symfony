<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/**
 * @author HypeMC <hypemc@gmail.com>
 */
class LazyProxyReferenceConfigurator extends ReferenceConfigurator
{
    /** @internal */
    protected string|array $interfaces;

    public function __construct(string $id, string|array $interfaces = [])
    {
        parent::__construct($id);

        $this->interfaces = $interfaces;
    }
}
