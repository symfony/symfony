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

class Source
{
    public function __construct(
        public Identity $identity = new Identity(),
        public Contact $contact = new Contact(),
        public Preferences $preferences = new Preferences(),
        public Location $location = new Location(),
        public string $nickname = 'jane',
        public string $login = 'jdoe',
        public array $roles = ['ROLE_USER'],
        public array $groups = ['admin'],
        public ?Profile $current = null,
    ) {
    }
}
