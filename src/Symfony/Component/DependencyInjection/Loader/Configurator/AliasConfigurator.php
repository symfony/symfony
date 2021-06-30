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

use Symfony\Component\DependencyInjection\Alias;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AliasConfigurator extends AbstractServiceConfigurator
{
    use Traits\DeprecateTrait;
    use Traits\PublicTrait;

    public const FACTORY = 'alias';

    public function __construct(ServicesConfigurator $parent, Alias $alias)
    {
        $this->parent = $parent;
        $this->definition = $alias;
    }
}
