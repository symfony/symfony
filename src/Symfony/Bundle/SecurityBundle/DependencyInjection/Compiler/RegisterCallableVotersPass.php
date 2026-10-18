<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Attribute\AsVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CallableVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Registers the methods and invokable classes marked with #[AsVoter] as voters.
 *
 * @internal
 */
final class RegisterCallableVotersPass implements CompilerPassInterface
{
    private const SUBJECT_BUILTIN_TYPES = ['null', 'bool', 'int', 'float', 'string', 'array', 'object'];
    private const RETURN_TYPES = ['bool', 'true', 'false', 'null', 'int', 'mixed'];

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAutoconfigured() && !$definition->isAbstract() && !$definition->hasTag('container.ignore_attributes') && $r = $container->getReflectionClass($definition->getClass(), false)) {
                self::checkAttributesCanBeHonored($r);
            }
        }

        foreach ($container->findTaggedServiceIds('.security.callable_voter', true) as $id => $tags) {
            $definition = $container->getDefinition($id);
            $definition->clearTag('.security.callable_voter');
            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }

            if ($definition->hasTag('security.voter')) {
                throw new InvalidArgumentException(\sprintf('Cannot use "#[AsVoter]" on "%s": the service "%s" is already a voter. To set its priority, use "#[AsTaggedItem]".', $r->name, $id));
            }

            foreach ($tags as $tag) {
                $method = $tag['method'] ?? '__invoke';

                if (!$r->hasMethod($method) || !$r->getMethod($method)->isPublic()) {
                    throw new InvalidArgumentException(\sprintf('Invalid voter "%s": method "%s::%s()" does not exist or is not public.', $id, $r->name, $method));
                }

                $attributes = (array) ($tag['attributes'] ?? []);

                if (!$attributes || !array_is_list($attributes) || array_any($attributes, static fn ($attribute) => !\is_string($attribute) || '' === $attribute)) {
                    throw new InvalidArgumentException(\sprintf('The "attributes" of the voter "%s::%s()" must be a non-empty string or a non-empty list of non-empty strings.', $r->name, $method));
                }

                if ($container->has($voterId = '.security.voter.'.$id.'.'.$method)) {
                    throw new InvalidArgumentException(\sprintf('The voter "%s::%s()" is declared twice. Mark it with "#[AsVoter]" once: on a class, the attribute declares the "__invoke()" method.', $r->name, $method));
                }

                $reflector = $r->getMethod($method);
                self::checkReturnType($reflector);
                [$arguments, $subject] = self::resolveArguments($reflector);

                $callable = (new Definition(\Closure::class))->setFactory([\Closure::class, 'fromCallable']);

                if ($reflector->isStatic()) {
                    $callable->setArguments([[$r->name, $method]]);
                } else {
                    // defers building the service until the voter calls it, without making it lazy elsewhere
                    $callable->setArguments([[new Reference($id), $method]])->setLazy(true);
                }

                $container->register($voterId, CallableVoter::class)
                    ->setArguments([
                        $callable,
                        $arguments,
                        $attributes,
                        $subject ? self::getSubjectTypes($reflector, $subject) : null,
                        $r->name.'::'.$method,
                    ])
                    ->addTag('security.voter', ['priority' => $tag['priority'] ?? 0]);
            }
        }
    }

    /**
     * Autoconfiguration reads #[AsVoter] on public methods only, so the attribute anywhere else would be silently ignored.
     */
    private static function checkAttributesCanBeHonored(\ReflectionClass $class): void
    {
        $types = $class->getInterfaces();
        for ($type = $class; $type; $type = $type->getParentClass()) {
            $types[] = $type;
        }

        foreach ($types as $type) {
            foreach ($type->getMethods() as $method) {
                if ($method->class !== $type->name || !$method->getAttributes(AsVoter::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                    continue;
                }

                if (!$method->isPublic()) {
                    throw new InvalidArgumentException(\sprintf('The "#[AsVoter]" attribute cannot be used on the non-public method "%s::%s()".', $method->class, $method->name));
                }

                if ($method->isAbstract() && !$class->getMethod($method->name)->getAttributes(AsVoter::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                    throw new InvalidArgumentException(\sprintf('The "#[AsVoter]" attribute cannot be used on the %s method "%s::%s()": declare it on its implementation "%s::%s()" instead.', $type->isInterface() ? 'interface' : 'abstract', $method->class, $method->name, $class->name, $method->name));
                }
            }
        }
    }

    private static function checkReturnType(\ReflectionMethod $method): void
    {
        if (!$type = $method->getReturnType()) {
            return;
        }

        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $namedType) {
            if (!$namedType instanceof \ReflectionNamedType || !\in_array($namedType->getName(), self::RETURN_TYPES, true)) {
                throw new InvalidArgumentException(\sprintf('The voter "%s::%s()" must return a boolean, null or one of the "%s::ACCESS_*" constants, "%s" declared.', $method->class, $method->name, VoterInterface::class, $type));
            }
        }
    }

    /**
     * @return array{list<'attribute'|'subject'|'token'|'vote'>, \ReflectionParameter|null}
     */
    private static function resolveArguments(\ReflectionMethod $method): array
    {
        $arguments = [];
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            $className = $type instanceof \ReflectionNamedType && !$type->isBuiltin() ? $typeName : null;

            if ($parameter->isVariadic()) {
                throw new InvalidArgumentException(\sprintf('The parameter "$%s" of the voter "%s::%s()" cannot be variadic.', $parameter->name, $method->class, $method->name));
            }

            if ('attribute' === $parameter->name) {
                if ($type && !\in_array($typeName, ['string', 'mixed'], true)) {
                    throw new InvalidArgumentException(\sprintf('The parameter "$attribute" of the voter "%s::%s()" receives the security attribute: type it as "string" or leave it untyped.', $method->class, $method->name));
                }

                $role = 'attribute';
            } elseif (TokenInterface::class === $typeName) {
                $role = 'token';
            } elseif (Vote::class === $typeName) {
                if (!$type->allowsNull()) {
                    throw new InvalidArgumentException(\sprintf('The parameter "$%s" of the voter "%s::%s()" must allow null: type it as "?Vote", as a voter can be called without a vote.', $parameter->name, $method->class, $method->name));
                }

                $role = 'vote';
            } elseif ($className && is_a($className, TokenInterface::class, true)) {
                throw new InvalidArgumentException(\sprintf('The parameter "$%s" of the voter "%s::%s()" receives the token: type it as "TokenInterface" and check the class of the token in the method.', $parameter->name, $method->class, $method->name));
            } elseif ($className && is_a($className, Vote::class, true)) {
                throw new InvalidArgumentException(\sprintf('The parameter "$%s" of the voter "%s::%s()" receives the vote: type it as "?Vote".', $parameter->name, $method->class, $method->name));
            } else {
                $role = 'subject';
            }

            if ('subject' === $role && isset($parameters['subject'])) {
                throw new InvalidArgumentException(\sprintf('Cannot tell which of the parameters "$%s" and "$%s" of the voter "%s::%s()" is the subject: only the token, the vote and the "$attribute" can be declared next to it.', $parameters['subject']->name, $parameter->name, $method->class, $method->name));
            }

            if (isset($parameters[$role])) {
                throw new InvalidArgumentException(\sprintf('The parameters "$%s" and "$%s" of the voter "%s::%s()" both receive the %s: declare it once.', $parameters[$role]->name, $parameter->name, $method->class, $method->name, $role));
            }

            $parameters[$role] = $parameter;
            $arguments[] = $role;
        }

        return [$arguments, $parameters['subject'] ?? null];
    }

    /**
     * @return list<string>|null
     */
    private static function getSubjectTypes(\ReflectionMethod $method, \ReflectionParameter $subject): ?array
    {
        if (!$type = $subject->getType()) {
            return null;
        }

        $types = [];
        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $namedType) {
            if (!$namedType instanceof \ReflectionNamedType || ($namedType->isBuiltin() && !\in_array($namedType->getName(), ['mixed', ...self::SUBJECT_BUILTIN_TYPES], true))) {
                throw new InvalidArgumentException(\sprintf('Cannot deduce the subjects voted on by "%s::%s()" from the type of its parameter "$%s": use classes, "%s" or "mixed".', $method->class, $method->name, $subject->name, implode('", "', self::SUBJECT_BUILTIN_TYPES)));
            }

            if ('mixed' === $namedType->getName()) {
                return null;
            }

            // self and parent are relative to the declaring class, and reflection does not resolve them in every case, like in trait methods
            $types[] = match ($namedType->getName()) {
                'self' => $method->getDeclaringClass()->name,
                'parent' => $method->getDeclaringClass()->getParentClass()->name,
                default => $namedType->getName(),
            };

            if ($namedType->allowsNull() && 'null' !== $namedType->getName()) {
                $types[] = 'null';
            }
        }

        return array_values(array_unique($types));
    }
}
