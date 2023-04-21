<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter;

use Symfony\Component\VarExporter\Exception\LogicException;
use Symfony\Component\VarExporter\Internal\Hydrator;
use Symfony\Component\VarExporter\Internal\LazyObjectRegistry;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ProxyHelper
{
    /**
     * Helps generate lazy-loading ghost objects.
     *
     * @throws LogicException When the class is incompatible with ghost objects
     */
    public static function generateLazyGhost(\ReflectionClass $class): string
    {
        if (\PHP_VERSION_ID >= 80200 && \PHP_VERSION_ID < 80300 && $class->isReadOnly()) {
            throw new LogicException(sprintf('Cannot generate lazy ghost: class "%s" is readonly.', $class->name));
        }
        if ($class->isFinal()) {
            throw new LogicException(sprintf('Cannot generate lazy ghost: class "%s" is final.', $class->name));
        }
        if ($class->isInterface() || $class->isAbstract()) {
            throw new LogicException(sprintf('Cannot generate lazy ghost: "%s" is not a concrete class.', $class->name));
        }
        if (\stdClass::class !== $class->name && $class->isInternal()) {
            throw new LogicException(sprintf('Cannot generate lazy ghost: class "%s" is internal.', $class->name));
        }
        if ($class->hasMethod('__get') && 'mixed' !== (self::exportType($class->getMethod('__get')) ?? 'mixed')) {
            throw new LogicException(sprintf('Cannot generate lazy ghost: return type of method "%s::__get()" should be "mixed".', $class->name));
        }

        static $traitMethods;
        $traitMethods ??= (new \ReflectionClass(LazyGhostTrait::class))->getMethods();

        foreach ($traitMethods as $method) {
            if ($class->hasMethod($method->name) && $class->getMethod($method->name)->isFinal()) {
                throw new LogicException(sprintf('Cannot generate lazy ghost: method "%s::%s()" is final.', $class->name, $method->name));
            }
        }

        $parent = $class;
        while ($parent = $parent->getParentClass()) {
            if (\stdClass::class !== $parent->name && $parent->isInternal()) {
                throw new LogicException(sprintf('Cannot generate lazy ghost: class "%s" extends "%s" which is internal.', $class->name, $parent->name));
            }
        }
        $propertyScopes = self::exportPropertyScopes($class->name);

        return <<<EOPHP
             extends \\{$class->name} implements \Symfony\Component\VarExporter\LazyObjectInterface
            {
                use \Symfony\Component\VarExporter\LazyGhostTrait;

                private const LAZY_OBJECT_PROPERTY_SCOPES = {$propertyScopes};
            }

            // Help opcache.preload discover always-needed symbols
            class_exists(\Symfony\Component\VarExporter\Internal\Hydrator::class);
            class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectRegistry::class);
            class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectState::class);

            EOPHP;
    }

    /**
     * Helps generate lazy-loading virtual proxies.
     *
     * @param \ReflectionClass[] $interfaces
     *
     * @throws LogicException When the class is incompatible with virtual proxies
     */
    public static function generateLazyProxy(?\ReflectionClass $class, array $interfaces = []): string
    {
        if (!class_exists($class?->name ?? \stdClass::class, false)) {
            throw new LogicException(sprintf('Cannot generate lazy proxy: "%s" is not a class.', $class->name));
        }
        if ($class?->isFinal()) {
            throw new LogicException(sprintf('Cannot generate lazy proxy: class "%s" is final.', $class->name));
        }
        if (\PHP_VERSION_ID >= 80200 && \PHP_VERSION_ID < 80300 && $class?->isReadOnly()) {
            throw new LogicException(sprintf('Cannot generate lazy proxy: class "%s" is readonly.', $class->name));
        }

        $methodReflectors = [$class?->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) ?? []];
        foreach ($interfaces as $interface) {
            if (!$interface->isInterface()) {
                throw new LogicException(sprintf('Cannot generate lazy proxy: "%s" is not an interface.', $interface->name));
            }
            $methodReflectors[] = $interface->getMethods();
        }
        $methodReflectors = array_merge(...$methodReflectors);

        $extendsInternalClass = false;
        if ($parent = $class) {
            do {
                $extendsInternalClass = \stdClass::class !== $parent->name && $parent->isInternal();
            } while (!$extendsInternalClass && $parent = $parent->getParentClass());
        }
        $methodsHaveToBeProxied = $extendsInternalClass;
        $methods = [];

        foreach ($methodReflectors as $method) {
            if ('__get' !== strtolower($method->name) || 'mixed' === ($type = self::exportType($method) ?? 'mixed')) {
                continue;
            }
            $methodsHaveToBeProxied = true;
            $trait = new \ReflectionMethod(LazyProxyTrait::class, '__get');
            $body = \array_slice(file($trait->getFileName()), $trait->getStartLine() - 1, $trait->getEndLine() - $trait->getStartLine());
            $body[0] = str_replace('): mixed', '): '.$type, $body[0]);
            $methods['__get'] = strtr(implode('', $body).'    }', [
                'Hydrator' => '\\'.Hydrator::class,
                'Registry' => '\\'.LazyObjectRegistry::class,
            ]);
            break;
        }

        foreach ($methodReflectors as $method) {
            if (($method->isStatic() && !$method->isAbstract()) || isset($methods[$lcName = strtolower($method->name)])) {
                continue;
            }
            if ($method->isFinal()) {
                if ($extendsInternalClass || $methodsHaveToBeProxied || method_exists(LazyProxyTrait::class, $method->name)) {
                    throw new LogicException(sprintf('Cannot generate lazy proxy: method "%s::%s()" is final.', $class->name, $method->name));
                }
                continue;
            }
            if (method_exists(LazyProxyTrait::class, $method->name) || ($method->isProtected() && !$method->isAbstract())) {
                continue;
            }

            $signature = self::exportSignature($method, true, $args);
            $parentCall = $method->isAbstract() ? "throw new \BadMethodCallException('Cannot forward abstract method \"{$method->class}::{$method->name}()\".')" : "parent::{$method->name}({$args})";

            if ($method->isStatic()) {
                $body = "        $parentCall;";
            } elseif (str_ends_with($signature, '): never') || str_ends_with($signature, '): void')) {
                $body = <<<EOPHP
                        if (isset(\$this->lazyObjectState)) {
                            (\$this->lazyObjectState->realInstance ??= (\$this->lazyObjectState->initializer)())->{$method->name}({$args});
                        } else {
                            {$parentCall};
                        }
                EOPHP;
            } else {
                if (!$methodsHaveToBeProxied && !$method->isAbstract()) {
                    // Skip proxying methods that might return $this
                    foreach (preg_split('/[()|&]++/', self::exportType($method) ?? 'static') as $type) {
                        if (\in_array($type = ltrim($type, '?'), ['static', 'object'], true)) {
                            continue 2;
                        }
                        foreach ([$class, ...$interfaces] as $r) {
                            if ($r && is_a($r->name, $type, true)) {
                                continue 3;
                            }
                        }
                    }
                }

                $body = <<<EOPHP
                        if (isset(\$this->lazyObjectState)) {
                            return (\$this->lazyObjectState->realInstance ??= (\$this->lazyObjectState->initializer)())->{$method->name}({$args});
                        }

                        return {$parentCall};
                EOPHP;
            }
            $methods[$lcName] = "    {$signature}\n    {\n{$body}\n    }";
        }

        $types = $interfaces = array_unique(array_column($interfaces, 'name'));
        $interfaces[] = LazyObjectInterface::class;
        $interfaces = implode(', \\', $interfaces);
        $parent = $class ? ' extends \\'.$class->name : '';
        array_unshift($types, $class ? 'parent' : '');
        $type = ltrim(implode('&\\', $types), '&');

        if (!$class) {
            $trait = new \ReflectionMethod(LazyProxyTrait::class, 'initializeLazyObject');
            $body = \array_slice(file($trait->getFileName()), $trait->getStartLine() - 1, $trait->getEndLine() - $trait->getStartLine());
            $body[0] = str_replace('): parent', '): '.$type, $body[0]);
            $methods = ['initializeLazyObject' => implode('', $body).'    }'] + $methods;
        }
        $body = $methods ? "\n".implode("\n\n", $methods)."\n" : '';
        $propertyScopes = $class ? self::exportPropertyScopes($class->name) : '[]';

        return <<<EOPHP
            {$parent} implements \\{$interfaces}
            {
                use \Symfony\Component\VarExporter\LazyProxyTrait;

                private const LAZY_OBJECT_PROPERTY_SCOPES = {$propertyScopes};
            {$body}}

            // Help opcache.preload discover always-needed symbols
            class_exists(\Symfony\Component\VarExporter\Internal\Hydrator::class);
            class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectRegistry::class);
            class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectState::class);

            EOPHP;
    }

    public static function exportSignature(\ReflectionFunctionAbstract $function, bool $withParameterTypes = true, string &$args = null): string
    {
        $hasByRef = false;
        $args = '';
        $param = null;
        $parameters = [];
        foreach ($function->getParameters() as $param) {
            $parameters[] = ($param->getAttributes(\SensitiveParameter::class) ? '#[\SensitiveParameter] ' : '')
                .($withParameterTypes && $param->hasType() ? self::exportType($param).' ' : '')
                .($param->isPassedByReference() ? '&' : '')
                .($param->isVariadic() ? '...' : '').'$'.$param->name
                .($param->isOptional() && !$param->isVariadic() ? ' = '.self::exportDefault($param) : '');
            $hasByRef = $hasByRef || $param->isPassedByReference();
            $args .= ($param->isVariadic() ? '...$' : '$').$param->name.', ';
        }

        if (!$param || !$hasByRef) {
            $args = '...\func_get_args()';
        } elseif ($param->isVariadic()) {
            $args = substr($args, 0, -2);
        } else {
            $args .= sprintf('...\array_slice(\func_get_args(), %d)', \count($parameters));
        }

        $signature = 'function '.($function->returnsReference() ? '&' : '')
            .($function->isClosure() ? '' : $function->name).'('.implode(', ', $parameters).')';

        if ($function instanceof \ReflectionMethod) {
            $signature = ($function->isPublic() ? 'public ' : ($function->isProtected() ? 'protected ' : 'private '))
                .($function->isStatic() ? 'static ' : '').$signature;
        }
        if ($function->hasReturnType()) {
            $signature .= ': '.self::exportType($function);
        }

        static $getPrototype;
        $getPrototype ??= (new \ReflectionMethod(\ReflectionMethod::class, 'getPrototype'))->invoke(...);

        while ($function) {
            if ($function->hasTentativeReturnType()) {
                return '#[\ReturnTypeWillChange] '.$signature;
            }

            try {
                $function = $function instanceof \ReflectionMethod && $function->isAbstract() ? false : $getPrototype($function);
            } catch (\ReflectionException) {
                break;
            }
        }

        return $signature;
    }

    public static function exportType(\ReflectionFunctionAbstract|\ReflectionProperty|\ReflectionParameter $owner, bool $noBuiltin = false, \ReflectionType $type = null): ?string
    {
        if (!$type ??= $owner instanceof \ReflectionFunctionAbstract ? $owner->getReturnType() : $owner->getType()) {
            return null;
        }
        $class = null;
        $types = [];
        if ($type instanceof \ReflectionUnionType) {
            $reflectionTypes = $type->getTypes();
            $glue = '|';
        } elseif ($type instanceof \ReflectionIntersectionType) {
            $reflectionTypes = $type->getTypes();
            $glue = '&';
        } else {
            $reflectionTypes = [$type];
            $glue = null;
        }

        foreach ($reflectionTypes as $type) {
            if ($type instanceof \ReflectionIntersectionType) {
                if ('' !== $name = '('.self::exportType($owner, $noBuiltin, $type).')') {
                    $types[] = $name;
                }
                continue;
            }
            $name = $type->getName();

            if ($noBuiltin && $type->isBuiltin()) {
                continue;
            }
            if (\in_array($name, ['parent', 'self'], true) && $class ??= $owner->getDeclaringClass()) {
                $name = 'parent' === $name ? ($class->getParentClass() ?: null)?->name ?? 'parent' : $class->name;
            }

            $types[] = ($noBuiltin || $type->isBuiltin() || 'static' === $name ? '' : '\\').$name;
        }

        if (!$types) {
            return '';
        }
        if (null === $glue) {
            return (!$noBuiltin && $type->allowsNull() && 'mixed' !== $name ? '?' : '').$types[0];
        }
        sort($types);

        return implode($glue, $types);
    }

    private static function exportPropertyScopes(string $parent): string
    {
        $propertyScopes = Hydrator::$propertyScopes[$parent] ??= Hydrator::getPropertyScopes($parent);
        uksort($propertyScopes, 'strnatcmp');
        $propertyScopes = VarExporter::export($propertyScopes);
        $propertyScopes = str_replace(VarExporter::export($parent), 'parent::class', $propertyScopes);
        $propertyScopes = preg_replace("/(?|(,)\n( )       |\n        |,\n    (\]))/", '$1$2', $propertyScopes);
        $propertyScopes = str_replace("\n", "\n    ", $propertyScopes);

        return $propertyScopes;
    }

    private static function exportDefault(\ReflectionParameter $param): string
    {
        $default = rtrim(substr(explode('$'.$param->name.' = ', (string) $param, 2)[1] ?? '', 0, -2));

        if (\in_array($default, ['<default>', 'NULL'], true)) {
            return 'null';
        }
        if (str_ends_with($default, "...'") && preg_match("/^'(?:[^'\\\\]*+(?:\\\\.)*+)*+'$/", $default)) {
            return VarExporter::export($param->getDefaultValue());
        }

        $regexp = "/(\"(?:[^\"\\\\]*+(?:\\\\.)*+)*+\"|'(?:[^'\\\\]*+(?:\\\\.)*+)*+')/";
        $parts = preg_split($regexp, $default, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);

        $regexp = '/([\[\( ]|^)([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z0-9_\x7f-\xff]++)*+)(?!: )/';
        $callback = (false !== strpbrk($default, "\\:('") && $class = $param->getDeclaringClass())
            ? fn ($m) => $m[1].match ($m[2]) {
                'new', 'false', 'true', 'null' => $m[2],
                'NULL' => 'null',
                'self' => '\\'.$class->name,
                'namespace\\parent',
                'parent' => ($parent = $class->getParentClass()) ? '\\'.$parent->name : 'parent',
                default => '\\'.$m[2],
            }
            : fn ($m) => $m[1].match ($m[2]) {
                'new', 'false', 'true', 'null', 'self', 'parent' => $m[2],
                'NULL' => 'null',
                default => '\\'.$m[2],
            };

        return implode('', array_map(fn ($part) => match ($part[0]) {
            '"' => $part, // for internal classes only
            "'" => false !== strpbrk($part, "\\\0\r\n") ? '"'.substr(str_replace(['$', "\0", "\r", "\n"], ['\$', '\0', '\r', '\n'], $part), 1, -1).'"' : $part,
            default => preg_replace_callback($regexp, $callback, $part),
        }, $parts));
    }
}
