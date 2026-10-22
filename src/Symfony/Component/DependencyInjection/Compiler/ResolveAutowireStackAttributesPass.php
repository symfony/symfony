<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Attribute\AutowireInline;
use Symfony\Component\DependencyInjection\Attribute\AutowireStack;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * @internal
 */
class ResolveAutowireStackAttributesPass extends ResolveAutowireInlineAttributesPass
{
    private \WeakMap $paths;
    private array $path;

    public function process(ContainerBuilder $container): void
    {
        $this->paths = new \WeakMap();
        $this->path = [];

        try {
            // Resolve inline attributes before inheritance to preserve their precedence over inherited arguments.
            (new ResolveDecoratorStackPass())->process($container);
            parent::process($container);

            do {
                (new ResolveDecoratorStackPass())->process($container);
                $paths = [];

                foreach ($container->getDefinitions() as $id => $definition) {
                    if (isset($this->paths[$definition])) {
                        $paths[$id] = $this->paths[$definition];
                    }
                }

                (new ResolveChildDefinitionsPass())->process($container);

                foreach ($paths as $id => $path) {
                    $this->paths[$container->getDefinition($id)] = $path;
                }

                (new ResolveParameterPlaceHoldersPass(false, false))->process($container);
                (new AutowireRequiredMethodsPass())->process($container);
                $count = \count($container->getDefinitions());

                parent::process($container);
            } while ($count !== \count($container->getDefinitions()));
        } finally {
            unset($this->paths, $this->path);
        }
    }

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if (!$value instanceof Definition) {
            return parent::processValue($value, $isRoot);
        }

        $path = $this->path;
        $this->path = $this->paths[$value] ?? ($isRoot ? [] : $path);

        try {
            return parent::processValue($value, $isRoot);
        } finally {
            $this->path = $path;
        }
    }

    protected function registerDefinition(string $id, Definition $definition, AutowireInline $attribute, \ReflectionParameter $parameter): void
    {
        $path = $this->path;

        if ($attribute instanceof AutowireStack) {
            $method = $parameter->getDeclaringFunction();
            $name = ($method instanceof \ReflectionMethod ? $method->class.'::' : '').$method->name.'($'.$parameter->name.')';

            foreach ($path as [$ancestorName, $ancestor]) {
                if ($name === $ancestorName && $attribute == $ancestor) {
                    throw new LogicException(\sprintf('Circular reference detected while expanding #[AutowireStack] for "%s".', $name));
                }
            }

            $path[] = [$name, $attribute];

            foreach ($definition->getArguments() as $frame) {
                $this->paths[$frame] = $path;
            }
        }

        $this->paths[$definition] = $path;
        parent::registerDefinition($id, $definition, $attribute, $parameter);
    }
}
