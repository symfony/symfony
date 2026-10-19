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

class ReplacingTransformTarget
{
    #[Map(source: 'identity')]
    #[Map(source: 'contact', transform: [self::class, 'toProfile'])]
    public Profile $profile;

    public static function toProfile(Contact $contact): Profile
    {
        $profile = new Profile();
        $profile->email = $contact->email;

        return $profile;
    }
}
