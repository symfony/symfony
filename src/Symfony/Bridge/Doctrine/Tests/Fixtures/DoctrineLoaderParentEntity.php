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

/**
 * @ORM\MappedSuperclass
 */
class DoctrineLoaderParentEntity
{
    /**
     * @ORM\Column(length=35)
     */
    public $publicParentMaxLength;

    /**
     * @ORM\Column(length=30)
     */
    private $privateParentMaxLength;

    public function getPrivateParentMaxLength()
    {
        return $this->privateParentMaxLength;
    }

    public function setPrivateParentMaxLength($privateParentMaxLength): void
    {
        $this->privateParentMaxLength = $privateParentMaxLength;
    }
}
