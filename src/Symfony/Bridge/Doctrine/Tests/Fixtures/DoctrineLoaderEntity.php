<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @ORM\Entity
 * @UniqueEntity(fields={"alreadyMappedUnique"})
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineLoaderEntity extends DoctrineLoaderParentEntity
{
    /**
     * @ORM\Id
     * @ORM\Column
     */
    public $id;

    /**
     * @ORM\Column(length=20)
     */
    public $maxLength;

    /**
     * @ORM\Column(length=20)
     */
    public $mergedMaxLength;

    /**
     * @ORM\Column(length=20)
     */
    public $alreadyMappedMaxLength;

    /**
     * @ORM\Column(unique=true)
     */
    public $unique;

    /**
     * @ORM\Column(unique=true)
     */
    public $alreadyMappedUnique;

    /**
     * @ORM\Embedded(class=DoctrineLoaderEmbed::class)
     */
    public $embedded;

    /** @ORM\Column(type="text", nullable=true, length=1000) */
    public $textField;

    /** @ORM\Id @ORM\Column(type="guid", length=50) */
    protected $guidField;

    /** @ORM\Column(type="simple_array", length=100) */
    public $simpleArrayField = [];

    /**
     * @ORM\Column(length=10)
     * @Assert\DisableAutoMapping
     */
    public $noAutoMapping;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $allowEmptyString = property_exists(Assert\Length::class, 'allowEmptyString') ? ['allowEmptyString' => true] : [];

        $metadata->addPropertyConstraint('mergedMaxLength', new Assert\Length(['min' => 5] + $allowEmptyString));
        $metadata->addPropertyConstraint('alreadyMappedMaxLength', new Assert\Length(['min' => 1, 'max' => 10] + $allowEmptyString));
    }
}
