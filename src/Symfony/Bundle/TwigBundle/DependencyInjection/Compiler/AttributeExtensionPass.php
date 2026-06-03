<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;
use Twig\Extension\AbstractExtension;
use Twig\Extension\AttributeExtension;
use Twig\Extension\ExtensionInterface;

/**
 * Register an instance of AttributeExtension for each service using the
 * PHP attributes to declare Twig callables.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * @internal
 */
final class AttributeExtensionPass implements CompilerPassInterface
{
    private const TAG = 'twig.attribute_extension';

    public static function autoconfigureFromAttribute(ChildDefinition $definition, AsTwigFilter|AsTwigFunction|AsTwigTest $attribute, \ReflectionMethod $reflector): void
    {
        $class = $reflector->getDeclaringClass();
        if ($class->implementsInterface(ExtensionInterface::class)) {
            if ($class->isSubclassOf(AbstractExtension::class)) {
                throw new LogicException(\sprintf('The class "%s" cannot extend "%s" and use the "#[%s]" attribute on method "%s()", choose one or the other.', $class->name, AbstractExtension::class, $attribute::class, $reflector->name));
            }
            throw new LogicException(\sprintf('The class "%s" cannot implement "%s" and use the "#[%s]" attribute on method "%s()", choose one or the other.', $class->name, ExtensionInterface::class, $attribute::class, $reflector->name));
        }

        $definition->addTag(self::TAG);

        // The service must be tagged as a runtime to call non-static methods
        if (!$reflector->isStatic()) {
            $definition->addTag('twig.runtime');
        }
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds(self::TAG, true) as $id => $tags) {
            $container->register('.twig.extension.'.$id, AttributeExtension::class)
                ->setArguments([$container->getDefinition($id)->getClass()])
                ->addTag('twig.extension');
        }
    }
}
