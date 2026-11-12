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

class ConstrainedOrder
{
    #[Assert\NotBlank]
    public string $reference = '';

    /** @var list<string> */
    #[Assert\NotBlank]
    public array $lines = [];

    /** @var array<string, string> */
    #[Assert\NotBlank]
    public array $notesByLine = [];

    /** @var array<string, int> */
    #[Assert\Count(min: 1, max: 3)]
    public array $quantitiesBySize = [];

    /** @var array<string, string> */
    #[Assert\Choice(choices: ['small', 'large'], multiple: true, min: 1, max: 2)]
    public array $sizesByLine = [];

    #[Assert\DivisibleBy(5)]
    public int $quantity = 5;

    #[Assert\DivisibleBy(-0.25)]
    public float $price = 1.0;

    #[Assert\Ip(version: Assert\Ip::V4_NO_PRIVATE)]
    public ?string $publicIpv4 = null;

    #[Assert\Ip(version: Assert\Ip::V6_ONLY_PUBLIC)]
    public ?string $publicIpv6 = null;
}
