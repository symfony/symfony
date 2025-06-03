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

use Doctrine\ORM\EntityRepository;

class SingleIntIdEntityRepository extends EntityRepository
{
    public $result = null;

    public function findByCustom()
    {
        return $this->result;
    }
}
