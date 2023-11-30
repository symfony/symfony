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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\VarExporter\ProxyHelper;

/**
 * Inspects existing autowired services for {@see AutowireInline} attribute and registers the definitions for reuse.
 *
 * @author Ismail Özgün Turan <oezguen.turan@dadadev.com>
 */
class ResolveAutowireInlineAttributesPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        $value = parent::processValue($value, $isRoot);

        if (!$value instanceof Definition || !$value->isAutowired() || $value->isAbstract() || !$value->getClass()) {
            return $value;
        }

        try {
            $constructor = $this->getConstructor($value, false);
        } catch (RuntimeException) {
            $this->container->log($this, sprintf('Skipping service "%s": Class or interface "%s" cannot be loaded.', $this->currentId, $value->getClass()));

            return $value;
        }

        if ($constructor === null) {
            return $value;
        }

        $reflectionParameters = $constructor->getParameters();
        foreach ($reflectionParameters as $reflectionParameter) {
            $autowireInlineAttributes = $reflectionParameter->getAttributes(AutowireInline::class, \ReflectionAttribute::IS_INSTANCEOF);
            foreach ($autowireInlineAttributes as $autowireInlineAttribute) {
                /** @var AutowireInline $autowireInlineAttributeInstance */
                $autowireInlineAttributeInstance = $autowireInlineAttribute->newInstance();

                $type = ProxyHelper::exportType($reflectionParameter, true);
                $definition = $autowireInlineAttributeInstance->buildDefinition($autowireInlineAttributeInstance->value, $type, $reflectionParameter);

                $this->container->setDefinition('.autowire_inline.'.ContainerBuilder::hash($definition), $definition);
            }
        }

        return $value;
    }
}
