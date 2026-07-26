<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummyThirdChild;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ClassDiscriminatorMappingTest extends TestCase
{
    public function testGetClass()
    {
        $mapping = new ClassDiscriminatorMapping('type', [
            'first' => AbstractDummyFirstChild::class,
        ]);

        $this->assertEquals(AbstractDummyFirstChild::class, $mapping->getClassForType('first'));
        $this->assertNull($mapping->getClassForType('second'));
    }

    public function testMappedObjectType()
    {
        $mapping = new ClassDiscriminatorMapping('type', [
            'first' => AbstractDummyFirstChild::class,
            'third' => AbstractDummyThirdChild::class,
        ]);

        $this->assertEquals('first', $mapping->getMappedObjectType(AbstractDummyFirstChild::class));
        $this->assertEquals('first', $mapping->getMappedObjectType(new AbstractDummyFirstChild()));
        $this->assertNull($mapping->getMappedObjectType(new AbstractDummySecondChild()));
        $this->assertSame('third', $mapping->getMappedObjectType(new AbstractDummyThirdChild()));
    }

    public function testMappedObjectTypeReadsDiscriminatorPropertyWhenSeveralTypesShareTheSameClass()
    {
        $mapping = new ClassDiscriminatorMapping('type', [
            'rf_actual' => DiscriminatorAmbiguousDocument::class,
            'rf_previous' => DiscriminatorAmbiguousDocument::class,
        ]);

        $actual = new DiscriminatorAmbiguousDocument();
        $actual->type = 'rf_actual';

        $previous = new DiscriminatorAmbiguousDocument();
        $previous->type = 'rf_previous';

        $this->assertSame('rf_actual', $mapping->getMappedObjectType($actual));
        $this->assertSame('rf_previous', $mapping->getMappedObjectType($previous));
    }

    public function testMappedObjectTypeUnwrapsBackedEnumDiscriminatorProperty()
    {
        $mapping = new ClassDiscriminatorMapping('type', [
            'rf_actual' => DiscriminatorEnumDocument::class,
            'rf_previous' => DiscriminatorEnumDocument::class,
        ]);

        $previous = new DiscriminatorEnumDocument();
        $previous->type = DiscriminatorEnumDocumentType::RF_PREVIOUS;

        $this->assertSame('rf_previous', $mapping->getMappedObjectType($previous));
    }

    public function testMappedObjectTypeFallsBackToFirstMatchWhenPropertyValueIsInvalid()
    {
        $mapping = new ClassDiscriminatorMapping('type', [
            'rf_actual' => DiscriminatorAmbiguousDocument::class,
            'rf_previous' => DiscriminatorAmbiguousDocument::class,
        ]);

        $invalid = new DiscriminatorAmbiguousDocument();
        $invalid->type = 'unknown';

        $this->assertSame('rf_actual', $mapping->getMappedObjectType($invalid));
    }

    public function testMappedObjectTypeIgnoresDiscriminatorPropertyWhenObjectClassIsNotAmbiguous()
    {
        $mapping = new ClassDiscriminatorMapping('type', [
            'base' => DiscriminatorBaseDocument::class,
            'child' => DiscriminatorChildDocument::class,
        ]);

        $child = new DiscriminatorChildDocument();
        $child->type = 'base';

        $this->assertSame('child', $mapping->getMappedObjectType($child));
    }

    public function testMappedObjectTypeKeepsTheMostSpecificTypeWhenTheParentClassIsMappedTwice()
    {
        $mapping = new ClassDiscriminatorMapping('type', [
            'base_a' => DiscriminatorBaseDocument::class,
            'base_b' => DiscriminatorBaseDocument::class,
            'child' => DiscriminatorChildDocument::class,
        ]);

        $child = new DiscriminatorChildDocument();
        $child->type = 'base_a';

        $this->assertSame('child', $mapping->getMappedObjectType($child));

        $base = new DiscriminatorBaseDocument();
        $base->type = 'base_b';

        $this->assertSame('base_b', $mapping->getMappedObjectType($base));
    }

    public function testMappedObjectTypeSupportsIntBackedEnums()
    {
        $mapping = new ClassDiscriminatorMapping('type', [
            1 => DiscriminatorIntEnumDocument::class,
            2 => DiscriminatorIntEnumDocument::class,
        ]);

        $document = new DiscriminatorIntEnumDocument();
        $document->type = DiscriminatorIntDocumentType::SECOND;

        $this->assertSame('2', $mapping->getMappedObjectType($document));
    }
}

class DiscriminatorAmbiguousDocument
{
    public ?string $type = null;
}

enum DiscriminatorEnumDocumentType: string
{
    case RF_ACTUAL = 'rf_actual';
    case RF_PREVIOUS = 'rf_previous';
}

class DiscriminatorEnumDocument
{
    public ?DiscriminatorEnumDocumentType $type = null;
}

class DiscriminatorBaseDocument
{
    public ?string $type = null;
}

class DiscriminatorChildDocument extends DiscriminatorBaseDocument
{
}

enum DiscriminatorIntDocumentType: int
{
    case FIRST = 1;
    case SECOND = 2;
}

class DiscriminatorIntEnumDocument
{
    public ?DiscriminatorIntDocumentType $type = null;
}
