<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Mapping;

use PHPStan\PhpDocParser\Ast\PhpDoc\ExtendsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ImplementsTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Symfony\Component\TypeInfo\Exception\ExceptionInterface;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

/**
 * Enhances properties metadata based on properties' generic type.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class GenericTypePropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    private ?Lexer $phpDocLexer = null;
    private ?PhpDocParser $phpDocParser = null;
    private ?StringTypeResolver $stringTypeResolver = null;

    public function __construct(
        private PropertyMetadataLoaderInterface $decorated,
        private TypeContextFactory $typeContextFactory,
    ) {
    }

    public function load(string $className, array $options = [], array $context = []): array
    {
        $result = $this->decorated->load($className, $options, $context);
        $classVariableTypes = $this->getClassVariableTypes($className, $context['original_type']);

        foreach ($result as &$metadata) {
            $variableTypes = [];

            if ($classVariableTypes) {
                // template names are the ones of the class that declares the property
                $name = $metadata->getName();
                $declaringClassName = null !== $name && property_exists($className, $name) ? (new \ReflectionProperty($className, $name))->class : (new \ReflectionClass($className))->name;
                $variableTypes = $classVariableTypes[$declaringClassName] ?? [];
            }

            $metadata = $metadata->withType($this->replaceVariableTypes($metadata->getType(), $variableTypes));
        }

        return $result;
    }

    /**
     * @param class-string $className
     *
     * @return array<class-string, array<string, Type>>
     */
    private function getClassVariableTypes(string $className, Type $type): array
    {
        $findTypeWithClassName = static function (string $className, Type $type) use (&$findTypeWithClassName): ?Type {
            if ($type instanceof UnionType || $type instanceof IntersectionType) {
                foreach ($type->getTypes() as $t) {
                    if (null !== $classType = $findTypeWithClassName($className, $t)) {
                        return $classType;
                    }
                }

                return null;
            }

            while ($type instanceof WrappingTypeInterface) {
                $baseType = $type;

                if ($type instanceof GenericType) {
                    foreach ($type->getVariableTypes() as $t) {
                        if (null !== $classType = $findTypeWithClassName($className, $t)) {
                            return $classType;
                        }
                    }
                }

                $type = $type->getWrappedType();

                if ($type instanceof ObjectType && $type->getClassName() === $className) {
                    return $baseType;
                }
            }

            return null;
        };

        if (null === $classType = $findTypeWithClassName($className, $type)) {
            return [];
        }

        $variableTypes = $classType instanceof GenericType ? $classType->getVariableTypes() : [];
        $templates = $this->typeContextFactory->createFromClassName($className)->templates;

        if (\count($templates) !== \count($variableTypes)) {
            throw new InvalidArgumentException(\sprintf('Given %d variable types in "%s", but %d templates are defined in "%2$s".', \count($variableTypes), $className, \count($templates)));
        }

        $templates = array_keys($templates);
        $classVariableTypes = [];

        foreach ($variableTypes as $i => $variableType) {
            $classVariableTypes[$templates[$i]] = $variableType;
        }

        return $classVariableTypes ? $this->resolveParentClassVariableTypes((new \ReflectionClass($className))->name, $classVariableTypes) : [];
    }

    /**
     * Resolves the variable types of the templates of a class and of its parents.
     *
     * A parent gets the variable types that its child gives in its "@extends" and "@implements" tags. When a child does not declare them, its parent class gets the variable types of the templates that have the same name.
     *
     * @param class-string        $className
     * @param array<string, Type> $variableTypes
     *
     * @return array<class-string, array<string, Type>>
     */
    private function resolveParentClassVariableTypes(string $className, array $variableTypes): array
    {
        $classVariableTypes = [$className => $variableTypes];
        $classNames = [$className];

        while (null !== $className = array_shift($classNames)) {
            foreach ($this->getParentVariableTypes($className) as $parentClassName => $parentVariableTypes) {
                if (isset($classVariableTypes[$parentClassName])) {
                    continue;
                }

                $classVariableTypes[$parentClassName] = [];
                $classNames[] = $parentClassName;

                foreach (array_keys($this->typeContextFactory->createFromClassName($parentClassName)->templates) as $i => $template) {
                    if (isset($parentVariableTypes[$i])) {
                        $classVariableTypes[$parentClassName][$template] = $this->replaceVariableTypes($parentVariableTypes[$i], $classVariableTypes[$className]);
                    }
                }
            }

            if (false !== ($parentClassName = get_parent_class($className)) && !isset($classVariableTypes[$parentClassName])) {
                $classVariableTypes[$parentClassName] = $classVariableTypes[$className];
                $classNames[] = $parentClassName;
            }
        }

        return $classVariableTypes;
    }

    /**
     * Returns the variable types that a class gives to its parents in its "@extends" and "@implements" tags.
     *
     * @param class-string $className
     *
     * @return array<class-string, list<Type>>
     */
    private function getParentVariableTypes(string $className): array
    {
        if (!class_exists(PhpDocParser::class) || false === $docComment = (new \ReflectionClass($className))->getDocComment()) {
            return [];
        }

        if (!$this->phpDocParser) {
            if (class_exists(ParserConfig::class)) {
                $config = new ParserConfig([]);
                $this->phpDocLexer = new Lexer($config);
                $this->phpDocParser = new PhpDocParser($config, new TypeParser($config, new ConstExprParser($config)), new ConstExprParser($config));
            } else {
                $this->phpDocLexer = new Lexer();
                $this->phpDocParser = new PhpDocParser(new TypeParser($constExprParser = new ConstExprParser(true, true), true), $constExprParser);
            }

            $this->stringTypeResolver = new StringTypeResolver();
        }

        $typeContext = $this->typeContextFactory->createFromClassName($className);
        $parentVariableTypes = [];

        foreach ($this->phpDocParser->parse(new TokenIterator($this->phpDocLexer->tokenize($docComment)))->getTags() as $tag) {
            if (!$tag->value instanceof ExtendsTagValueNode && !$tag->value instanceof ImplementsTagValueNode) {
                continue;
            }

            try {
                $type = $this->stringTypeResolver->resolve((string) $tag->value->type, $typeContext);
            } catch (ExceptionInterface) {
                continue;
            }

            if ($type instanceof CollectionType) {
                $type = $type->getWrappedType();
            }

            if ($type instanceof GenericType && ($parentType = $type->getWrappedType()) instanceof ObjectType && is_subclass_of($className, $parentType->getClassName())) {
                $parentVariableTypes[(new \ReflectionClass($parentType->getClassName()))->name] ??= $type->getVariableTypes();
            }
        }

        return $parentVariableTypes;
    }

    /**
     * @param array<string, Type> $variableTypes
     */
    private function replaceVariableTypes(Type $type, array $variableTypes): Type
    {
        return $type->map(static fn (Type $t): Type => $t instanceof TemplateType ? ($variableTypes[$t->getName()] ?? $t) : $t);
    }
}
