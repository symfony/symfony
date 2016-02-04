<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures\HighestEntity;

use Doctrine\ORM\Mapping\MappedSuperclass;

/** @MappedSuperclass */
class SideMappedSuperClass extends GoodEntity
{
}
