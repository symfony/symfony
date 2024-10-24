<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Dummy extends ParentDummy
{
    /**
     * @var string This is bar
     */
    private $bar;

    /**
     * Should be used.
     *
     * @var int Should be ignored
     */
    protected $baz;

    /**
     * #@+
     * A short description ignoring template.
     *
     *
     * A long description...
     *
     * ...over several lines.
     *
     * @var \DateTimeImmutable
     */
    public $bal;

    /**
     * @var ParentDummy
     */
    public $parent;

    /**
     * @var \DateTimeImmutable[]
     */
    #[Groups(['a', 'b'])]
    public $collection;

    /**
     * @var DummyCollection<int, string>
     */
    public $collectionAsObject;

    /**
     * @var string[][]
     */
    public $nestedCollection;

    /**
     * @var mixed[]
     */
    public $mixedCollection;

    /**
     * @var ParentDummy
     */
    public $B;

    /**
     * @var int
     */
    protected $Id;

    /**
     * @var string
     */
    public $Guid;

    /**
     * Nullable array.
     *
     * @var array|null
     */
    public $g;

    /**
     * @var ?string
     */
    public $h;

    /**
     * @var string|int|null
     */
    public $i;

    /**
     * @var ?\DateTimeImmutable
     */
    public $j;

    /**
     * @var int[]|null
     */
    public $nullableCollectionOfNonNullableElements;

    /**
     * @var array<null|int>
     */
    public $nonNullableCollectionOfNullableElements;

    /**
     * @var null|array<int|string>
     */
    public $nullableCollectionOfMultipleNonNullableElementTypes;

    /**
     * @var array
     */
    private $xTotals;

    /**
     * @var string
     */
    private $YT;

    /**
     * This should not be removed.
     *
     * @var
     */
    public $emptyVar;

    /**
     * @var \Iterator<string>
     */
    public $iteratorCollection;

    /**
     * @var \Iterator<integer,string>
     */
    public $iteratorCollectionWithKey;

    /**
     * @var \Iterator<integer,\Iterator<integer,string>>
     */
    public $nestedIterators;

    /**
     * @var array<string,string>
     */
    public $arrayWithKeys;

    /**
     * @var array<string,array<integer,null|string>|null>
     */
    public $arrayWithKeysAndComplexValue;

    /**
     * @var array<string,mixed>
     */
    public $arrayOfMixed;

    public $noDocBlock;

    /**
     * @var list<string>
     */
    public $listOfStrings;

    /**
     * @var parent
     */
    public $parentAnnotation;

    /**
     * @var \BackedEnum<string>
     */
    public $genericInterface;

    /** @var Dummy[]|null  */
    public $nullableTypedCollection = null;

    public static function getStatic()
    {
    }

    /**
     * @return string
     */
    public static function staticGetter()
    {
    }

    public static function staticSetter(\DateTimeImmutable $d)
    {
    }

    /**
     * A.
     *
     * @return int
     */
    public function getA()
    {
    }

    /**
     * B.
     *
     * @param ParentDummy|null $parent
     */
    public function setB(?ParentDummy $parent = null)
    {
    }

    /**
     * Date of Birth.
     *
     * @return \DateTimeImmutable
     */
    public function getDOB()
    {
    }

    /**
     * @return int
     */
    public function getId()
    {
    }

    public function get123()
    {
    }

    /**
     * @param self $self
     */
    public function setSelf(self $self)
    {
    }

    /**
     * @param parent $realParent
     */
    public function setRealParent(parent $realParent)
    {
    }

    /**
     * @return array
     */
    public function getXTotals()
    {
    }

    /**
     * @return string
     */
    public function getYT()
    {
    }

    public function setDate(\DateTimeImmutable $date)
    {
    }

    public function addDate(\DateTimeImmutable $date)
    {
    }

    public function hasElement(string $element): bool
    {
    }

    public function addNullableTypedCollection(Dummy $dummy): void
    {
    }
}
