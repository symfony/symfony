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

use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class InlineServiceConfigurator extends AbstractConfigurator
{
    const FACTORY = 'service';

    use Traits\ArgumentTrait;
    use Traits\AutowireTrait;
    use Traits\BindTrait;
    use Traits\CallTrait;
    use Traits\ConfiguratorTrait;
    use Traits\FactoryTrait;
    use Traits\FileTrait;
    use Traits\LazyTrait;
    use Traits\ParentTrait;
    use Traits\PropertyTrait;
    use Traits\TagTrait;

    private $id = '[inline]';
    private $allowParent = true;
    private $path = null;

    public function __construct(Definition $definition)
    {
        $this->definition = $definition;
    }
}
