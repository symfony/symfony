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

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractRecursivePass implements CompilerPassInterface
{
    protected $container;
    protected $currentId;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;

        try {
            $this->processValue($container->getDefinitions(), true);
        } finally {
            $this->container = null;
        }
    }

    /**
     * Processes a value found in a definition tree.
     *
     * @param mixed $value
     * @param bool  $isRoot
     *
     * @return mixed The processed value
     */
    protected function processValue($value, $isRoot = false)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if ($isRoot) {
                    $this->currentId = $k;
                }
                if ($v !== $processedValue = $this->processValue($v, $isRoot)) {
                    $value[$k] = $processedValue;
                }
            }
        } elseif ($value instanceof ArgumentInterface) {
            $value->setValues($this->processValue($value->getValues()));
        } elseif ($value instanceof Definition) {
            $value->setArguments($this->processValue($value->getArguments()));
            $value->setProperties($this->processValue($value->getProperties()));
            $value->setOverriddenGetters($this->processValue($value->getOverriddenGetters()));
            $value->setMethodCalls($this->processValue($value->getMethodCalls()));

            if ($v = $value->getFactory()) {
                $value->setFactory($this->processValue($v));
            }
            if ($v = $value->getConfigurator()) {
                $value->setConfigurator($this->processValue($v));
            }
        }

        return $value;
    }
}
