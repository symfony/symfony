<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess;

class DateSource
{
    public \DateTime $at;

    public function __construct()
    {
        $this->at = new \DateTime('2024-01-02 03:04:05');
    }
}
