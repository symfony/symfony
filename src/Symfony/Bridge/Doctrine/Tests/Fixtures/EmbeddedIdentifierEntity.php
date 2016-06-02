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
 * @ORM\Entity
 */
class EmbeddedIdentifierEntity
{
    /**
     * @var Embeddable\Identifier
     *
     * @ORM\Embedded(class="Symfony\Bridge\Doctrine\Tests\Fixtures\Embeddable\Identifier")
     */
    protected $id;
}
