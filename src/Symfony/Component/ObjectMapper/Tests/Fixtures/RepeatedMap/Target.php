<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\RepeatedMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

class Target
{
    #[Map(source: 'identity')]
    #[Map(source: 'contact')]
    public Profile $profile;
}
