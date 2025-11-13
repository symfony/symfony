<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * XmlDumper dumps a service container as an XML string.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class XmlDumper extends Dumper
{
    /**
     * Dumps the service container as an XML string.
     */
    public function dump(array $options = []): string
    {
        $xml = <<<EOXML
            <?xml version="1.0" encoding="utf-8"?>
            <container xmlns="http://symfony.com/schema/dic/services" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://symfony.com/schema/dic/services https://symfony.com/schema/dic/services/services-1.0.xsd">
            EOXML;

        foreach ($this->addParameters() as $line) {
            $xml .= "\n  ".$line;
        }
        foreach ($this->addServices() as $line) {
            $xml .= "\n  ".$line;
        }

        $xml .= "\n</container>\n";

        return $this->container->resolveEnvPlaceholders($xml);
    }

    private function addParameters(): iterable
    {
        if (!$data = $this->container->getParameterBag()->all()) {
            return;
        }

        if ($this->container->isCompiled()) {
            $data = $this->escape($data);
        }

        yield '<parameters>';
        foreach ($this->convertParameters($data, 'parameter') as $line) {
            yield '  '.$line;
        }
        yield '</parameters>';
    }

    private function addMethodCalls(array $methodcalls): iterable
    {
        foreach ($methodcalls as $methodcall) {
            $xmlAttr = \sprintf(' method="%s"%s', $this->encode($methodcall[0]), ($methodcall[2] ?? false) ? ' returns-clone="true"' : '');

            if ($methodcall[1]) {
                yield \sprintf('<call%s>', $xmlAttr);
                foreach ($this->convertParameters($methodcall[1], 'argument') as $line) {
                    yield '  '.$line;
                }
                yield '</call>';
            } else {
                yield \sprintf('<call%s/>', $xmlAttr);
            }
        }
    }

    private function addService(Definition $definition, ?string $id): iterable
    {
        $xmlAttr = '';
        if (null !== $id) {
            $xmlAttr .= \sprintf(' id="%s"', $this->encode($id));
        }
        if ($class = $definition->getClass()) {
            if (str_starts_with($class, '\\')) {
                $class = substr($class, 1);
            }

            $xmlAttr .= \sprintf(' class="%s"', $this->encode($class));
        }
        if (!$definition->isShared()) {
            $xmlAttr .= ' shared="false"';
        }
        if ($definition->isPublic()) {
            $xmlAttr .= ' public="true"';
        }
        if ($definition->isSynthetic()) {
            $xmlAttr .= ' synthetic="true"';
        }
        if ($definition->isLazy()) {
            $xmlAttr .= ' lazy="true"';
        }
        if (null !== $decoratedService = $definition->getDecoratedService()) {
            [$decorated, $renamedId, $priority] = $decoratedService;
            $xmlAttr .= \sprintf(' decorates="%s"', $this->encode($decorated));

            $decorationOnInvalid = $decoratedService[3] ?? ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            if (\in_array($decorationOnInvalid, [ContainerInterface::IGNORE_ON_INVALID_REFERENCE, ContainerInterface::NULL_ON_INVALID_REFERENCE], true)) {
                $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE === $decorationOnInvalid ? 'null' : 'ignore';
                $xmlAttr .= \sprintf(' decoration-on-invalid="%s"', $invalidBehavior);
            }
            if (null !== $renamedId) {
                $xmlAttr .= \sprintf(' decoration-inner-name="%s"', $this->encode($renamedId));
            }
            if (0 !== $priority) {
                $xmlAttr .= \sprintf(' decoration-priority="%d"', $priority);
            }
        }

        $xml = [];

        $tags = $definition->getTags();
        $tags['container.error'] = array_map(fn ($e) => ['message' => $e], $definition->getErrors());
        foreach ($tags as $name => $tags) {
            foreach ($tags as $attributes) {
                // Check if we have recursive attributes
                if (array_filter($attributes, \is_array(...))) {
                    $xml[] = \sprintf('  <tag name="%s">', $this->encode($name));
                    foreach ($this->addTagRecursiveAttributes($attributes) as $line) {
                        $xml[] = '    '.$line;
                    }
                    $xml[] = '  </tag>';
                } else {
                    $hasNameAttr = \array_key_exists('name', $attributes);
                    $attr = \sprintf(' name="%s"', $this->encode($hasNameAttr ? $attributes['name'] : $name));
                    foreach ($attributes as $key => $value) {
                        if ('name' !== $key) {
                            $attr .= \sprintf(' %s="%s"', $this->encode($key), $this->encode(self::phpToXml($value ?? '')));
                        }
                    }
                    if ($hasNameAttr) {
                        $xml[] = \sprintf('  <tag%s>%s</tag>', $attr, $this->encode($name, 0));
                    } else {
                        $xml[] = \sprintf('  <tag%s/>', $attr);
                    }
                }
            }
        }

        if ($definition->getFile()) {
            $xml[] = \sprintf('  <file>%s</file>', $this->encode($definition->getFile(), 0));
        }

        foreach ($this->convertParameters($definition->getArguments(), 'argument') as $line) {
            $xml[] = '  '.$line;
        }

        foreach ($this->convertParameters($definition->getProperties(), 'property', 'name') as $line) {
            $xml[] = '  '.$line;
        }

        foreach ($this->addMethodCalls($definition->getMethodCalls()) as $line) {
            $xml[] = '  '.$line;
        }

        if ($callable = $definition->getFactory()) {
            if (\is_array($callable) && ['Closure', 'fromCallable'] !== $callable && $definition->getClass() === $callable[0]) {
                $xmlAttr .= \sprintf(' constructor="%s"', $this->encode($callable[1]));
            } else {
                if (\is_array($callable) && $callable[0] instanceof Definition) {
                    $xml[] = \sprintf('  <factory method="%s">', $this->encode($callable[1]));
                    foreach ($this->addService($callable[0], null) as $line) {
                        $xml[] = '    '.$line;
                    }
                    $xml[] = '  </factory>';
                } elseif (\is_array($callable)) {
                    if (null !== $callable[0]) {
                        $xml[] = \sprintf('  <factory %s="%s" method="%s"/>', $callable[0] instanceof Reference ? 'service' : 'class', $this->encode($callable[0]), $this->encode($callable[1]));
                    } else {
                        $xml[] = \sprintf('  <factory method="%s"/>', $this->encode($callable[1]));
                    }
                } else {
                    $xml[] = \sprintf('  <factory function="%s"/>', $this->encode($callable));
                }
            }
        }

        if ($definition->isDeprecated()) {
            $deprecation = $definition->getDeprecation('%service_id%');
            $xml[] = \sprintf('  <deprecated package="%s" version="%s">%s</deprecated>', $this->encode($deprecation['package']), $this->encode($deprecation['version']), $this->encode($deprecation['message'], 0));
        }

        if ($definition->isAutowired()) {
            $xmlAttr .= ' autowire="true"';
        }

        if ($definition->isAutoconfigured()) {
            $xmlAttr .= ' autoconfigure="true"';
        }

        if ($definition->isAbstract()) {
            $xmlAttr .= ' abstract="true"';
        }

        if ($callable = $definition->getConfigurator()) {
            if (\is_array($callable) && $callable[0] instanceof Definition) {
                $xml[] = \sprintf('  <configurator method="%s">', $this->encode($callable[1]));
                foreach ($this->addService($callable[0], null) as $line) {
                    $xml[] = '    '.$line;
                }
                $xml[] = '  </configurator>';
            } elseif (\is_array($callable)) {
                $xml[] = \sprintf('  <configurator %s="%s" method="%s"/>', $callable[0] instanceof Reference ? 'service' : 'class', $this->encode($callable[0]), $this->encode($callable[1]));
            } else {
                $xml[] = \sprintf('  <configurator function="%s"/>', $this->encode($callable));
            }
        }

        if (!$xml) {
            yield \sprintf('<service%s/>', $xmlAttr);
        } else {
            yield \sprintf('<service%s>', $xmlAttr);
            yield from $xml;
            yield '</service>';
        }
    }

    private function addServiceAlias(string $alias, Alias $id): iterable
    {
        $xmlAttr = \sprintf(' id="%s" alias="%s"%s', $this->encode($alias), $this->encode($id), $id->isPublic() ? ' public="true"' : '');

        if ($id->isDeprecated()) {
            $deprecation = $id->getDeprecation('%alias_id%');
            yield \sprintf('<service%s>', $xmlAttr);
            yield \sprintf('  <deprecated package="%s" version="%s">%s</deprecated>', $this->encode($deprecation['package']), $this->encode($deprecation['version']), $this->encode($deprecation['message'], 0));
            yield '</service>';
        } else {
            yield \sprintf('<service%s/>', $xmlAttr);
        }
    }

    private function addServices(): iterable
    {
        if (!$definitions = $this->container->getDefinitions()) {
            return;
        }

        yield '<services>';
        foreach ($definitions as $id => $definition) {
            foreach ($this->addService($definition, $id) as $line) {
                yield '  '.$line;
            }
        }

        $aliases = $this->container->getAliases();
        foreach ($aliases as $alias => $id) {
            while (isset($aliases[(string) $id])) {
                $id = $aliases[(string) $id];
            }
            foreach ($this->addServiceAlias($alias, $id) as $line) {
                yield '  '.$line;
            }
        }
        yield '</services>';
    }

    private function addTagRecursiveAttributes(array $attributes): iterable
    {
        foreach ($attributes as $name => $value) {
            if (\is_array($value)) {
                yield \sprintf('<attribute name="%s">', $this->encode($name));
                foreach ($this->addTagRecursiveAttributes($value) as $line) {
                    yield '  '.$line;
                }
                yield '</attribute>';
            } elseif ('' !== $value = self::phpToXml($value ?? '')) {
                yield \sprintf('<attribute name="%s">%s</attribute>', $this->encode($name), $this->encode($value, 0));
            }
        }
    }

    private function convertParameters(array $parameters, string $type, string $keyAttribute = 'key'): iterable
    {
        $withKeys = !array_is_list($parameters);
        foreach ($parameters as $key => $value) {
            $xmlAttr = $withKeys ? \sprintf(' %s="%s"', $keyAttribute, $this->encode($key)) : '';

            if (($value instanceof TaggedIteratorArgument && $tag = $value)
                || ($value instanceof ServiceLocatorArgument && $tag = $value->getTaggedIteratorArgument())
            ) {
                $xmlAttr .= \sprintf(' type="%s"', $value instanceof TaggedIteratorArgument ? 'tagged_iterator' : 'tagged_locator');
                $xmlAttr .= \sprintf(' tag="%s"', $this->encode($tag->getTag()));

                if (null !== $tag->getIndexAttribute()) {
                    $xmlAttr .= \sprintf(' index-by="%s"', $this->encode($tag->getIndexAttribute()));

                    if (null !== $tag->getDefaultIndexMethod()) {
                        $xmlAttr .= \sprintf(' default-index-method="%s"', $this->encode($tag->getDefaultIndexMethod()));
                    }
                    if (null !== $tag->getDefaultPriorityMethod()) {
                        $xmlAttr .= \sprintf(' default-priority-method="%s"', $this->encode($tag->getDefaultPriorityMethod()));
                    }
                }
                if (1 === \count($excludes = $tag->getExclude())) {
                    $xmlAttr .= \sprintf(' exclude="%s"', $this->encode($excludes[0]));
                }
                if (!$tag->excludeSelf()) {
                    $xmlAttr .= ' exclude-self="false"';
                }

                if (1 < \count($excludes)) {
                    yield \sprintf('<%s%s>', $type, $xmlAttr);
                    foreach ($excludes as $exclude) {
                        yield \sprintf('  <exclude>%s</exclude>', $this->encode($exclude, 0));
                    }
                    yield \sprintf('</%s>', $type);
                } else {
                    yield \sprintf('<%s%s/>', $type, $xmlAttr);
                }
            } elseif (match (true) {
                \is_array($value) && $xmlAttr .= ' type="collection"' => true,
                $value instanceof IteratorArgument && $xmlAttr .= ' type="iterator"' => true,
                $value instanceof ServiceLocatorArgument && $xmlAttr .= ' type="service_locator"' => true,
                $value instanceof ServiceClosureArgument && !$value->getValues()[0] instanceof Reference && $xmlAttr .= ' type="service_closure"' => true,
                default => false,
            }) {
                if ($value instanceof ArgumentInterface) {
                    $value = $value->getValues();
                }
                if ($value) {
                    yield \sprintf('<%s%s>', $type, $xmlAttr);
                    foreach ($this->convertParameters($value, $type, 'key') as $line) {
                        yield '  '.$line;
                    }
                    yield \sprintf('</%s>', $type);
                } else {
                    yield \sprintf('<%s%s/>', $type, $xmlAttr);
                }
            } elseif ($value instanceof Reference || $value instanceof ServiceClosureArgument) {
                if ($value instanceof ServiceClosureArgument) {
                    $xmlAttr .= ' type="service_closure"';
                    $value = $value->getValues()[0];
                } else {
                    $xmlAttr .= ' type="service"';
                }
                $xmlAttr .= \sprintf(' id="%s"', $this->encode((string) $value));
                $xmlAttr .= match ($value->getInvalidBehavior()) {
                    ContainerInterface::NULL_ON_INVALID_REFERENCE => ' on-invalid="null"',
                    ContainerInterface::IGNORE_ON_INVALID_REFERENCE => ' on-invalid="ignore"',
                    ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE => ' on-invalid="ignore_uninitialized"',
                    default => '',
                };

                yield \sprintf('<%s%s/>', $type, $xmlAttr);
            } elseif ($value instanceof Definition) {
                $xmlAttr .= ' type="service"';

                yield \sprintf('<%s%s>', $type, $xmlAttr);
                foreach ($this->addService($value, null) as $line) {
                    yield '  '.$line;
                }
                yield \sprintf('</%s>', $type);
            } else {
                if ($value instanceof Expression) {
                    $xmlAttr .= ' type="expression"';
                    $value = (string) $value;
                } elseif (\is_string($value) && !preg_match('/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*+$/u', $value)) {
                    $xmlAttr .= ' type="binary"';
                    $value = base64_encode($value);
                } elseif ($value instanceof \UnitEnum) {
                    $xmlAttr .= ' type="constant"';
                } elseif ($value instanceof AbstractArgument) {
                    $xmlAttr .= ' type="abstract"';
                    $value = $value->getText();
                } elseif (\in_array($value, ['null', 'true', 'false'], true)) {
                    $xmlAttr .= ' type="string"';
                } elseif (\is_string($value) && (is_numeric($value) || preg_match('/^0b[01]*$/', $value) || preg_match('/^0x[0-9a-f]++$/i', $value))) {
                    $xmlAttr .= ' type="string"';
                }

                if ('' === $value = self::phpToXml($value)) {
                    yield \sprintf('<%s%s/>', $type, $xmlAttr);
                } else {
                    yield \sprintf('<%s%s>%s</%1$s>', $type, $xmlAttr, $this->encode($value, 0));
                }
            }
        }
    }

    private function encode(string $value, int $flags = \ENT_COMPAT): string
    {
        return str_replace("\r", '&#13;', htmlspecialchars($value, \ENT_XML1 | \ENT_SUBSTITUTE | $flags, 'UTF-8'));
    }

    private function escape(array $arguments): array
    {
        $args = [];
        foreach ($arguments as $k => $v) {
            $args[$k] = match (true) {
                \is_array($v) => $this->escape($v),
                \is_string($v) => str_replace('%', '%%', $v),
                default => $v,
            };
        }

        return $args;
    }

    /**
     * Converts php types to xml types.
     *
     * @throws RuntimeException When trying to dump object or resource
     */
    public static function phpToXml(mixed $value): string
    {
        return match (true) {
            null === $value => 'null',
            true === $value => 'true',
            false === $value => 'false',
            $value instanceof Parameter => '%'.$value.'%',
            $value instanceof \UnitEnum => \sprintf('%s::%s', $value::class, $value->name),
            \is_object($value),
            \is_resource($value) => throw new RuntimeException(\sprintf('Unable to dump a service container if a parameter is an object or a resource, got "%s".', get_debug_type($value))),
            default => (string) $value,
        };
    }
}
