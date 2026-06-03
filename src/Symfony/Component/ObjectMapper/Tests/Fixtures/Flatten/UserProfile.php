<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\Flatten;

readonly class UserProfile
{
    public function __construct(public string $firstName, public string $lastName)
    {
    }

    public static function getFirstName($v, $object)
    {
        return $v->firstName;
    }

    public static function getLastName($v, $object)
    {
        return $v->lastName;
    }
}
