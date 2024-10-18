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

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: TargetUser::class)]
readonly class User
{
    public function __construct(
        #[Map(transform: [UserProfile::class, 'getFirstName'], target: 'firstName')]
        #[Map(transform: [UserProfile::class, 'getLastName'], target: 'lastName')]
        public UserProfile $profile,
        public string $email,
    ) {
    }
}
