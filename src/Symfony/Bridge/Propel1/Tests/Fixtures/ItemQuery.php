<?php

/*
 * this file is part of the symfony package.
 *
 * (c) fabien potencier <fabien@symfony.com>
 *
 * for the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Tests\Fixtures;

class ItemQuery
{
    public function getTableMap()
    {
        // Allows to define methods in this class
        // to avoid a lot of mock classes
        return $this;
    }

    public function getPrimaryKeys()
    {
        return array('id');
    }
}
