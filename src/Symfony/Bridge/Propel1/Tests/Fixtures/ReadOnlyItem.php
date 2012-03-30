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

use \PropelPDO;

class ReadOnlyItem extends \BaseObject
{
    public function getName()
    {
        return 'Marvin';
    }

    public function getPrimaryKey()
    {
        return 42;
    }
}
