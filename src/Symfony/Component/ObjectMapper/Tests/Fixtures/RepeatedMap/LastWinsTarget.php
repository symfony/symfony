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

class LastWinsTarget
{
    #[Map(source: 'nickname')]
    #[Map(source: 'login')]
    public string $username;

    #[Map(source: 'roles')]
    #[Map(source: 'groups')]
    public array $permissions;
}
