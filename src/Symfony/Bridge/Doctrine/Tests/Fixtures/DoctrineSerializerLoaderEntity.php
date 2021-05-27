<?php

declare(strict_types=1);

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class DoctrineSerializerLoaderEntity
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     */
    public $id;

    /**
     * @ORM\Column(type="date")
     */
    public $dateMutable;

    /**
     * @ORM\Column(type="date_immutable")
     */
    public $dateImmutable;

    /**
     * @ORM\Column(type="time")
     */
    private $timeMutable;

    /**
     * @ORM\Column(type="time_immutable")
     */
    private $timeImmutable;
}
