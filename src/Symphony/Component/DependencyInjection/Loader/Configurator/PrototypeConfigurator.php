<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Loader\Configurator;

use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class PrototypeConfigurator extends AbstractServiceConfigurator
{
    const FACTORY = 'load';

    use Traits\AbstractTrait;
    use Traits\ArgumentTrait;
    use Traits\AutoconfigureTrait;
    use Traits\AutowireTrait;
    use Traits\BindTrait;
    use Traits\CallTrait;
    use Traits\ConfiguratorTrait;
    use Traits\DeprecateTrait;
    use Traits\FactoryTrait;
    use Traits\LazyTrait;
    use Traits\ParentTrait;
    use Traits\PropertyTrait;
    use Traits\PublicTrait;
    use Traits\ShareTrait;
    use Traits\TagTrait;

    private $loader;
    private $resource;
    private $exclude;
    private $allowParent;

    public function __construct(ServicesConfigurator $parent, PhpFileLoader $loader, Definition $defaults, string $namespace, string $resource, bool $allowParent)
    {
        $definition = new Definition();
        $definition->setPublic($defaults->isPublic());
        $definition->setAutowired($defaults->isAutowired());
        $definition->setAutoconfigured($defaults->isAutoconfigured());
        $definition->setBindings($defaults->getBindings());
        $definition->setChanges(array());

        $this->loader = $loader;
        $this->resource = $resource;
        $this->allowParent = $allowParent;

        parent::__construct($parent, $definition, $namespace, $defaults->getTags());
    }

    public function __destruct()
    {
        parent::__destruct();

        if ($this->loader) {
            $this->loader->registerClasses($this->definition, $this->id, $this->resource, $this->exclude);
        }
        $this->loader = null;
    }

    /**
     * Excludes files from registration using a glob pattern.
     *
     * @return $this
     */
    final public function exclude(string $exclude)
    {
        $this->exclude = $exclude;

        return $this;
    }
}
