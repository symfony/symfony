<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Configurator;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;

/**
 * @template T of 'array'|'variable'|'scalar'|'string'|'boolean'|'integer'|'float'|'enum' = 'array'
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class DefinitionConfigurator
{
    /**
     * @param TreeBuilder<T> $treeBuilder
     */
    public function __construct(
        private TreeBuilder $treeBuilder,
        private DefinitionFileLoader $loader,
        private string $path,
        private string $file,
    ) {
    }

    public function import(string $resource, ?string $type = null, bool $ignoreErrors = false): void
    {
        $this->loader->setCurrentDir(\dirname($this->path));
        $this->loader->import($resource, $type, $ignoreErrors, $this->file);
    }

    /**
     * @return (
     *    T is 'array' ? ArrayNodeDefinition<TreeBuilder<T>>
     *    : (T is 'variable' ? VariableNodeDefinition<TreeBuilder<T>>
     *    : (T is 'scalar' ? ScalarNodeDefinition<TreeBuilder<T>>
     *    : (T is 'string' ? StringNodeDefinition<TreeBuilder<T>>
     *    : (T is 'boolean' ? BooleanNodeDefinition<TreeBuilder<T>>
     *    : (T is 'integer' ? IntegerNodeDefinition<TreeBuilder<T>>
     *    : (T is 'float' ? FloatNodeDefinition<TreeBuilder<T>>
     *    : (T is 'enum' ? EnumNodeDefinition<TreeBuilder<T>>
     *    : NodeDefinition<TreeBuilder<T>>)))))))
     * )
     */
    public function rootNode(): NodeDefinition
    {
        return $this->treeBuilder->getRootNode();
    }

    public function setPathSeparator(string $separator): void
    {
        $this->treeBuilder->setPathSeparator($separator);
    }
}
