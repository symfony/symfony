<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Translation\DependencyInjection;

use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds tagged translation.formatter services to translation writer.
 */
class TranslationDumperPass implements CompilerPassInterface
{
    private $writerServiceId;
    private $dumperTag;

    public function __construct(string $writerServiceId = 'translation.writer', string $dumperTag = 'translation.dumper')
    {
        $this->writerServiceId = $writerServiceId;
        $this->dumperTag = $dumperTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->writerServiceId)) {
            return;
        }

        $definition = $container->getDefinition($this->writerServiceId);

        foreach ($container->findTaggedServiceIds($this->dumperTag, true) as $id => $attributes) {
            $definition->addMethodCall('addDumper', array($attributes[0]['alias'], new Reference($id)));
        }
    }
}
