<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Ip;

class ContactWithFormatConstraints
{
    #[Ip]
    public ?string $ipv4 = null;

    #[Ip(version: Ip::V6)]
    public ?string $ipv6 = null;

    #[Ip(version: Ip::ALL)]
    public ?string $anyIp = null;

    #[Assert\Hostname]
    public ?string $host = null;

    #[Assert\Date]
    public ?string $birthday = null;

    #[Assert\DateTime]
    public ?string $lastSeen = null;

    #[Assert\Time]
    public ?string $wakeUp = null;

    #[Assert\Uuid]
    public ?string $uuid = null;

    #[Assert\Ulid]
    public ?string $ulid = null;

    /** @var list<string> */
    #[Assert\Choice(choices: ['email', 'sms', 'push'], multiple: true, min: 1, max: 2)]
    public array $channels = [];
}
