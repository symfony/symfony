<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Extractor;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\KeywordsChild;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\KeywordsGrandParent;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\KeywordsParent;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\OtherNamespace\KeywordsChild as KeywordsChildInOtherNamespace;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;

class ClassKeywordsResolutionTest extends TestCase
{
    public function testSelfStaticAndParentAreResolvedTheSameWayByAllExtractors()
    {
        $expected = [];
        $actual = [];

        foreach ([new PhpDocExtractor(), new PhpStanExtractor()] as $extractor) {
            foreach ([KeywordsParent::class, KeywordsChild::class, KeywordsChildInOtherNamespace::class] as $class) {
                $type = $this->createExpectedType(KeywordsParent::class, $class, KeywordsGrandParent::class);

                foreach (['prop', 'promoted', 'accessor', 'mutator', 'traitProp', 'traitAccessor', 'traitMutator'] as $property) {
                    $key = \sprintf('%s::getType() for %s::$%s', $extractor::class, $class, $property);
                    $expected[$key] = $type;
                    $actual[$key] = $extractor->getType($class, $property);
                }

                foreach (['promoted', 'argument'] as $property) {
                    $key = \sprintf('%s::getTypeFromConstructor() for %s::$%s', $extractor::class, $class, $property);
                    $expected[$key] = $type;
                    $actual[$key] = $extractor->getTypeFromConstructor($class, $property);
                }
            }
        }

        $mismatches = array_keys(array_filter($actual, static fn ($type, $key) => $type != $expected[$key], \ARRAY_FILTER_USE_BOTH));

        $this->assertEquals($expected, $actual, "Unexpected types:\n".implode("\n", $mismatches));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSelfStaticAndParentAreResolvedTheSameWayByAllExtractorsLegacy()
    {
        $expected = [];
        $actual = [];

        foreach ([new PhpDocExtractor(), new PhpStanExtractor()] as $extractor) {
            foreach ([KeywordsParent::class, KeywordsChild::class, KeywordsChildInOtherNamespace::class] as $class) {
                $types = $this->createExpectedLegacyTypes(KeywordsParent::class, $class, KeywordsGrandParent::class);

                foreach (['prop', 'promoted', 'accessor', 'mutator', 'traitProp', 'traitAccessor', 'traitMutator'] as $property) {
                    $key = \sprintf('%s::getTypes() for %s::$%s', $extractor::class, $class, $property);
                    $expected[$key] = $types;
                    $actual[$key] = $extractor->getTypes($class, $property);
                }

                foreach (['promoted', 'argument'] as $property) {
                    $key = \sprintf('%s::getTypesFromConstructor() for %s::$%s', $extractor::class, $class, $property);
                    $expected[$key] = $types;
                    $actual[$key] = $extractor->getTypesFromConstructor($class, $property);
                }
            }
        }

        $mismatches = array_keys(array_filter($actual, static fn ($types, $key) => $types != $expected[$key], \ARRAY_FILTER_USE_BOTH));

        $this->assertEquals($expected, $actual, "Unexpected types:\n".implode("\n", $mismatches));
    }

    private function createExpectedType(string $self, string $static, string $parent): Type
    {
        $self = Type::object($self);
        $static = Type::object($static);
        $parent = Type::object($parent);

        return Type::union(
            $self,
            $static,
            $parent,
            Type::list(Type::union($self, $static, $parent)),
            Type::array(Type::nullable($static), Type::string()),
            Type::collection(Type::object(\ArrayObject::class), $parent, Type::int()),
        );
    }

    private function createExpectedLegacyTypes(string $self, string $static, string $parent): array
    {
        $self = new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, $self);
        $static = new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, $static);
        $parent = new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, $parent);

        return [
            $self,
            $static,
            $parent,
            new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), [$self, $static, $parent]),
            new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, $static->getClassName())),
            new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, \ArrayObject::class, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), $parent),
            $static,
        ];
    }
}
