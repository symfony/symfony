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

use Doctrine\DBAL\Result;
use Doctrine\ORM\AbstractQuery;

class LegacyQueryMock extends AbstractQuery
{
    public function __construct()
    {
    }

    /**
     * @return array|string
     */
    public function getSQL()
    {
    }

    /**
     * @return Result|int
     */
    protected function _doExecute()
    {
    }
}
