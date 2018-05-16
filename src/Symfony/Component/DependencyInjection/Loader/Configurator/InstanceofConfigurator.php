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
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @method InstanceofConfigurator instanceof(string $fqcn)
 */
class InstanceofConfigurator extends AbstractServiceConfigurator
{
    const FACTORY = 'instanceof';

    use Traits\AutowireTrait;
    use Traits\CallTrait;
    use Traits\ConfiguratorTrait;
    use Traits\LazyTrait;
    use Traits\PropertyTrait;
    use Traits\PublicTrait;
    use Traits\ShareTrait;
    use Traits\TagTrait;

    /**
     * Defines an instanceof-conditional to be applied to following service definitions.
     *
     * @param string $fqcn
     *
     * @return self
     */
    final protected function setInstanceof($fqcn)
    {
        return $this->parent->instanceof($fqcn);
    }
}
