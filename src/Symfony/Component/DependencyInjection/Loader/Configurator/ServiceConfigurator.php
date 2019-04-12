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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ServiceConfigurator extends AbstractServiceConfigurator
{
    const FACTORY = 'services';

    use Traits\AbstractTrait;
    use Traits\ArgumentTrait;
    use Traits\AutoconfigureTrait;
    use Traits\AutowireTrait;
    use Traits\BindTrait;
    use Traits\CallTrait;
    use Traits\ClassTrait;
    use Traits\ConfiguratorTrait;
    use Traits\DecorateTrait;
    use Traits\DeprecateTrait;
    use Traits\FactoryTrait;
    use Traits\FileTrait;
    use Traits\LazyTrait;
    use Traits\ParentTrait;
    use Traits\PropertyTrait;
    use Traits\PublicTrait;
    use Traits\ShareTrait;
    use Traits\SyntheticTrait;
    use Traits\TagTrait;

    private $container;
    private $instanceof;
    private $allowParent;

    public function __construct(ContainerBuilder $container, array $instanceof, $allowParent, ServicesConfigurator $parent, Definition $definition, $id, array $defaultTags)
    {
        $this->container = $container;
        $this->instanceof = $instanceof;
        $this->allowParent = $allowParent;

        parent::__construct($parent, $definition, $id, $defaultTags);
    }

    public function __destruct()
    {
        parent::__destruct();

        $this->container->addRemovedBindingIds($this->id);

        if (!$this->definition instanceof ChildDefinition) {
            $this->container->setDefinition($this->id, $this->definition->setInstanceofConditionals($this->instanceof));
        } else {
            $this->container->setDefinition($this->id, $this->definition);
        }
    }
}
