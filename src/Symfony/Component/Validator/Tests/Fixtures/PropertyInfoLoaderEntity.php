<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoLoaderEntity
{
    public $nullableString;
    public $string;
    public $scalar;
    public $object;
    public $collection;
    public $collectionOfUnknown;

    /**
     * @Assert\Type(type="int")
     */
    public $alreadyMappedType;

    /**
     * @Assert\NotNull
     */
    public $alreadyMappedNotNull;

    /**
     * @Assert\NotBlank
     */
    public $alreadyMappedNotBlank;

    /**
     * @Assert\All({
     *     @Assert\Type(type="string"),
     *     @Assert\Iban
     * })
     */
    public $alreadyPartiallyMappedCollection;

    public $readOnly;

    /**
     * @Assert\DisableAutoMapping
     */
    public $noAutoMapping;

    public function setNonExistentField()
    {
    }
}
