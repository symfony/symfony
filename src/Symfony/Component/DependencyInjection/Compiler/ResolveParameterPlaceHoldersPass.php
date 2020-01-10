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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * Resolves all parameter placeholders "%somevalue%" to their real values.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ResolveParameterPlaceHoldersPass extends AbstractRecursivePass
{
    private $bag;
    private $resolveArrays;
    private $throwOnResolveException;

    public function __construct($resolveArrays = true, $throwOnResolveException = true)
    {
        $this->resolveArrays = $resolveArrays;
        $this->throwOnResolveException = $throwOnResolveException;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ParameterNotFoundException
     */
    public function process(ContainerBuilder $container)
    {
        $this->bag = $container->getParameterBag();

        try {
            parent::process($container);

            $aliases = [];
            foreach ($container->getAliases() as $name => $target) {
                $this->currentId = $name;
                $aliases[$this->bag->resolveValue($name)] = $target;
            }
            $container->setAliases($aliases);
        } catch (ParameterNotFoundException $e) {
            $e->setSourceId($this->currentId);

            throw $e;
        }

        $this->bag->resolve();
        $this->bag = null;
    }

    protected function processValue($value, bool $isRoot = false)
    {
        if (\is_string($value)) {
            try {
                $v = $this->bag->resolveValue($value);
            } catch (ParameterNotFoundException $e) {
                if ($this->throwOnResolveException) {
                    throw $e;
                }

                $v = null;
                $this->container->getDefinition($this->currentId)->addError($e->getMessage());
            }

            return $this->resolveArrays || !$v || !\is_array($v) ? $v : $value;
        }
        if ($value instanceof Definition) {
            $value->setBindings($this->processValue($value->getBindings()));
            $changes = $value->getChanges();
            if (isset($changes['class'])) {
                $value->setClass($this->bag->resolveValue($value->getClass()));
            }
            if (isset($changes['file'])) {
                $value->setFile($this->bag->resolveValue($value->getFile()));
            }
        }

        $value = parent::processValue($value, $isRoot);

        if ($value && \is_array($value)) {
            $value = array_combine($this->bag->resolveValue(array_keys($value)), $value);
        }

        return $value;
    }
}
