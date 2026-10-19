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

class TransformTarget
{
    #[Map(source: 'identity')]
    #[Map(source: 'contact', transform: [self::class, 'upperCaseEmail'])]
    public Profile $profile;

    public static function upperCaseEmail(Contact $contact): Contact
    {
        return new Contact(strtoupper($contact->email));
    }
}
