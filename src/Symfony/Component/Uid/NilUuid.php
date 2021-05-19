<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid;

class NilUuid extends Uuid
{
    protected const TYPE = -1;

    public function __construct()
    {
        $this->uid = '00000000-0000-0000-0000-000000000000';
    }
}
