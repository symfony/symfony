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

class RefinedProperties
{
    /** @var int<1, max> */
    public int $page;
    /** @var positive-int */
    public int $quantity;
    /** @var int<0, 100>|null */
    public ?int $percentage;
    /** @var 'draft'|'live' */
    public string $status;
}
