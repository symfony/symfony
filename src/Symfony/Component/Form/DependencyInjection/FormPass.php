<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\DataClassType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Util\StringUtil;

/**
 * Adds all services with the tags "form.type", "form.type_extension" and
 * "form.type_guesser" as arguments of the "form.extension" service.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('form.extension')) {
            return;
        }

        $definition = $container->getDefinition('form.extension');
        $definition->replaceArgument(0, $this->processFormTypes($container));
        $definition->replaceArgument(1, $this->processFormTypeExtensions($container));
        $definition->replaceArgument(2, $this->processFormTypeGuessers($container));
    }

    private function processFormTypes(ContainerBuilder $container): Reference
    {
        // Get service locator argument
        $servicesMap = [];
        $namespaces = ['Symfony\Component\Form\Extension\Core\Type' => true];
        $csrfTokenIds = [];

        // Builds an array with fully-qualified type class names as keys and service IDs as values
        foreach ($container->findTaggedServiceIds('form.type', true) as $serviceId => $tag) {
            // Add form type service to the service locator
            $serviceDefinition = $container->getDefinition($serviceId);
            $servicesMap[$formType = $serviceDefinition->getClass()] = new Reference($serviceId);
            $namespaces[substr($formType, 0, strrpos($formType, '\\') ?: \strlen($formType))] = true;

            if (isset($tag[0]['csrf_token_id'])) {
                $csrfTokenIds[$formType] = $tag[0]['csrf_token_id'];
            }
        }

        $dataClasses = [];
        // abstract data classes get a type too, they can be the parent type of concrete ones
        foreach (array_keys($container->findTaggedResourceIds('form.data_class', false)) as $id) {
            $class = $container->getDefinition($id)->getClass();
            $container->setDefinition($typeId = '.form.data_class_type.'.$class, $this->createDataClassTypeDefinition($container, $class));

            $servicesMap[$class] = new Reference($typeId);
            $dataClasses[] = $class;
            $namespaces[substr($class, 0, strrpos($class, '\\') ?: \strlen($class))] = true;
        }

        if ($container->hasDefinition('console.command.form_debug')) {
            $commandDefinition = $container->getDefinition('console.command.form_debug');
            $commandDefinition->setArgument(1, array_keys($namespaces));
            $commandDefinition->setArgument(2, array_keys($servicesMap));
            $commandDefinition->setArgument('$dataClassTypes', $dataClasses);
        }

        if ($csrfTokenIds && $container->hasDefinition('form.type_extension.csrf')) {
            $csrfExtension = $container->getDefinition('form.type_extension.csrf');

            if (8 <= \count($csrfExtension->getArguments())) {
                $csrfExtension->replaceArgument(7, $csrfTokenIds);
            }
        }

        return ServiceLocatorTagPass::register($container, $servicesMap);
    }

    private function processFormTypeExtensions(ContainerBuilder $container): array
    {
        $typeExtensions = [];
        $typeExtensionsClasses = [];
        foreach ($this->findAndSortTaggedServices('form.type_extension', $container) as $reference) {
            $serviceId = (string) $reference;
            $serviceDefinition = $container->getDefinition($serviceId);

            $tag = $serviceDefinition->getTag('form.type_extension');
            $typeExtensionClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());

            if (isset($tag[0]['extended_type'])) {
                $typeExtensions[$tag[0]['extended_type']][] = new Reference($serviceId);
                $typeExtensionsClasses[] = $typeExtensionClass;
            } else {
                $extendsTypes = false;

                $typeExtensionsClasses[] = $typeExtensionClass;
                $container->getReflectionClass($typeExtensionClass);
                foreach ($typeExtensionClass::getExtendedTypes() as $extendedType) {
                    $typeExtensions[$extendedType][] = new Reference($serviceId);
                    $extendsTypes = true;
                }

                if (!$extendsTypes) {
                    throw new InvalidArgumentException(\sprintf('The getExtendedTypes() method for service "%s" does not return any extended types.', $serviceId));
                }
            }
        }

        foreach ($typeExtensions as $extendedType => $extensions) {
            $typeExtensions[$extendedType] = new IteratorArgument($extensions);
        }

        if ($container->hasDefinition('console.command.form_debug')) {
            $commandDefinition = $container->getDefinition('console.command.form_debug');
            $commandDefinition->setArgument(3, $typeExtensionsClasses);
        }

        return $typeExtensions;
    }

    private function processFormTypeGuessers(ContainerBuilder $container): ArgumentInterface
    {
        $guessers = [];
        $guessersClasses = [];
        foreach ($container->findTaggedServiceIds('form.type_guesser', true) as $serviceId => $tags) {
            $guessers[] = new Reference($serviceId);

            $serviceDefinition = $container->getDefinition($serviceId);
            $guessersClasses[] = $serviceDefinition->getClass();
        }

        if ($container->hasDefinition('console.command.form_debug')) {
            $commandDefinition = $container->getDefinition('console.command.form_debug');
            $commandDefinition->setArgument(4, $guessersClasses);
        }

        return new IteratorArgument($guessers);
    }

    private function createDataClassTypeDefinition(ContainerBuilder $container, string $class): Definition
    {
        $reflector = $container->getReflectionClass($class);

        if (!$attributes = $reflector->getAttributes(AsFormType::class, \ReflectionAttribute::IS_INSTANCEOF)) {
            throw new InvalidArgumentException(\sprintf('The class "%s" is tagged "form.data_class" but has no #[AsFormType] attribute.', $class));
        }
        $asFormData = $attributes[0]->newInstance();
        $options = $asFormData->options;

        if (\array_key_exists('data_class', $options)) {
            throw new InvalidArgumentException(\sprintf('The "data_class" option cannot be set on the #[AsFormType] attribute of class "%s", the class itself is the data class.', $class));
        }
        $this->validateOptionValues($options, \sprintf('the #[AsFormType] attribute of class "%s"', $class));

        $fields = [];
        foreach ($reflector->getProperties() as $property) {
            if ($property->getDeclaringClass()->name !== $reflector->name || !$fieldAttributes = $property->getAttributes(FormField::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                continue;
            }
            $field = $fieldAttributes[0]->newInstance();
            $context = \sprintf('the #[FormField] attribute of property "%s::$%s"', $class, $property->name);

            if (null !== $field->type && !class_exists($field->type)) {
                throw new InvalidArgumentException(\sprintf('The form type "%s" declared on %s does not exist.', $field->type, $context));
            }

            $name = $property->name;
            $fieldOptions = $field->options;
            if (null !== $field->name) {
                if (isset($fieldOptions['property_path'])) {
                    throw new InvalidArgumentException(\sprintf('The "name" argument of %s cannot be combined with the "property_path" option.', $context));
                }
                $fieldOptions['property_path'] = $name;
                $name = $field->name;
            }
            if (isset($fields[$name])) {
                throw new InvalidArgumentException(\sprintf('Duplicate field "%s" declared on %s.', $name, $context));
            }
            $this->validateOptionValues($fieldOptions, $context);

            $fields[$name] = [$field->type, $fieldOptions];
        }

        $parent = FormType::class;
        $parentReflector = $reflector;
        while ($parentReflector = $parentReflector->getParentClass()) {
            if ($parentReflector->getAttributes(AsFormType::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                $parent = $parentReflector->name;
                break;
            }
        }

        // escape option values so that "%" is kept verbatim when the definition is resolved
        $parameterBag = $container->getParameterBag();

        return new Definition(DataClassType::class, [$class, $parent, StringUtil::fqcnToBlockPrefix($class) ?: '', $parameterBag->escapeValue($fields), $parameterBag->escapeValue($options)]);
    }

    private function validateOptionValues(array $options, string $context, string $path = ''): void
    {
        foreach ($options as $name => $value) {
            $name = $path ? $path.'['.$name.']' : $name;

            if (\is_array($value)) {
                $this->validateOptionValues($value, $context, $name);
            } elseif (!\is_scalar($value) && null !== $value && !$value instanceof \UnitEnum && !$value instanceof Reference) {
                throw new InvalidArgumentException(\sprintf('The "%s" option declared on %s contains a value of type "%s"; only scalar, array, enum and service reference values can be compiled. Use a form type class or a type extension for dynamic options.', $name, $context, get_debug_type($value)));
            }
        }
    }
}
