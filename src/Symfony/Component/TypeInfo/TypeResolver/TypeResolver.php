<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\TypeResolver;

use PHPStan\PhpDocParser\Parser\PhpDocParser;
use Psr\Container\ContainerInterface;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;

/**
 * Resolves type for a given subject by delegating resolving to nested type resolvers.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
final readonly class TypeResolver implements TypeResolverInterface
{
    /**
     * @param ContainerInterface $resolvers Locator of type resolvers, keyed by supported subject type
     */
    public function __construct(
        private ContainerInterface $resolvers,
    ) {
    }

    public function resolve(mixed $subject, ?TypeContext $typeContext = null): Type
    {
        $subjectType = match (\is_object($subject)) {
            true => match (true) {
                is_subclass_of($subject::class, \ReflectionType::class) => \ReflectionType::class,
                is_subclass_of($subject::class, \ReflectionFunctionAbstract::class) => \ReflectionFunctionAbstract::class,
                default => $subject::class,
            },
            false => get_debug_type($subject),
        };

        if (!$this->resolvers->has($subjectType)) {
            if ('string' === $subjectType) {
                throw new UnsupportedException('Cannot find any resolver for "string" type. Try running "composer require phpstan/phpdoc-parser".', $subject);
            }

            throw new UnsupportedException(\sprintf('Cannot find any resolver for "%s" type.', $subjectType), $subject);
        }

        /** @param TypeResolverInterface $resolver */
        $resolver = $this->resolvers->get($subjectType);

        return $resolver->resolve($subject, $typeContext);
    }

    /**
     * @param array<string, TypeResolverInterface>|null $resolvers
     */
    public static function create(?array $resolvers = null): self
    {
        if (null === $resolvers) {
            $stringTypeResolver = class_exists(PhpDocParser::class) ? new StringTypeResolver() : null;
            $typeContextFactory = new TypeContextFactory($stringTypeResolver);
            $reflectionTypeResolver = new ReflectionTypeResolver();

            $resolvers = [
                \ReflectionType::class => $reflectionTypeResolver,
                \ReflectionParameter::class => new ReflectionParameterTypeResolver($reflectionTypeResolver, $typeContextFactory),
                \ReflectionProperty::class => new ReflectionPropertyTypeResolver($reflectionTypeResolver, $typeContextFactory),
                \ReflectionFunctionAbstract::class => new ReflectionReturnTypeResolver($reflectionTypeResolver, $typeContextFactory),
            ];

            if (null !== $stringTypeResolver) {
                $resolvers['string'] = $stringTypeResolver;
                $resolvers[\ReflectionParameter::class] = new PhpDocAwareReflectionTypeResolver($resolvers[\ReflectionParameter::class], $stringTypeResolver, $typeContextFactory);
                $resolvers[\ReflectionProperty::class] = new PhpDocAwareReflectionTypeResolver($resolvers[\ReflectionProperty::class], $stringTypeResolver, $typeContextFactory);
                $resolvers[\ReflectionFunctionAbstract::class] = new PhpDocAwareReflectionTypeResolver($resolvers[\ReflectionFunctionAbstract::class], $stringTypeResolver, $typeContextFactory);
            }
        }

        $resolversContainer = new class($resolvers) implements ContainerInterface {
            public function __construct(
                private readonly array $resolvers,
            ) {
            }

            public function has(string $id): bool
            {
                return isset($this->resolvers[$id]);
            }

            public function get(string $id): TypeResolverInterface
            {
                return $this->resolvers[$id];
            }
        };

        return new self($resolversContainer);
    }
}
