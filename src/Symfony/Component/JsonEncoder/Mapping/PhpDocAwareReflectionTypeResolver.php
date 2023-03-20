<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Mapping;

use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Resolves type on reflection priorizing PHP documentation.
 *
 * @internal
 */
final readonly class PhpDocAwareReflectionTypeResolver implements TypeResolverInterface
{
    private ?PhpDocParser $phpDocParser;
    private ?Lexer $lexer;

    public function __construct(
        private TypeResolverInterface $typeResolver,
        private TypeContextFactory $typeContextFactory,
    ) {
        $this->phpDocParser = class_exists(PhpDocParser::class) ? new PhpDocParser(new TypeParser(), new ConstExprParser()) : null;
        $this->lexer = class_exists(PhpDocParser::class) ? new Lexer() : null;
    }

    public function resolve(mixed $subject, TypeContext $typeContext = null): Type
    {
        if (!$subject instanceof \ReflectionProperty && !$subject instanceof \ReflectionParameter && !$subject instanceof \ReflectionFunctionAbstract) {
            throw new UnsupportedException(sprintf('Expected subject to be a "ReflectionProperty", a "ReflectionParameter" or a "ReflectionFunctionAbstract", "%s" given.', get_debug_type($subject)), $subject);
        }

        if (!$this->phpDocParser) {
            return $this->typeResolver->resolve($subject);
        }

        $docComment = match (true) {
            $subject instanceof \ReflectionProperty => $subject->getDocComment(),
            $subject instanceof \ReflectionParameter => $subject->getDeclaringFunction()->getDocComment(),
            $subject instanceof \ReflectionFunctionAbstract => $subject->getDocComment(),
        };

        if (!$docComment) {
            return $this->typeResolver->resolve($subject);
        }

        $typeContext ??= $this->typeContextFactory->createFromReflection($subject);

        $tagName = match (true) {
            $subject instanceof \ReflectionProperty => '@var',
            $subject instanceof \ReflectionParameter => '@param',
            $subject instanceof \ReflectionFunctionAbstract => '@return',
        };

        $tokens = new TokenIterator($this->lexer->tokenize($docComment));
        $docNode = $this->phpDocParser->parse($tokens);

        foreach ($docNode->getTagsByName($tagName) as $tag) {
            $tagValue = $tag->value;

            if (
                $tagValue instanceof VarTagValueNode
                || $tagValue instanceof ParamTagValueNode && $tagName && '$'.$subject->getName() === $tagValue->parameterName
                || $tagValue instanceof ReturnTagValueNode
            ) {
                return $this->typeResolver->resolve((string) $tagValue, $typeContext);
            }
        }

        return $this->typeResolver->resolve($subject);
    }
}
