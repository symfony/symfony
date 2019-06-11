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
 * @ORM\Embeddable
 */
class DoctrineLoaderEmbed
{
    /**
     * @ORM\Column(length=25)
     */
    public $embeddedMaxLength;

    /**
     * @ORM\Embedded(class=DoctrineLoaderNestedEmbed::class)
     */
    public $nestedEmbedded;
}
