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

class EnumCollectionDefaults
{
    /** @var list<Suit> */
    public array $suits = [Suit::Hearts];

    /** @var array<string, list<Color>> */
    public array $palettes = ['warm' => [Color::Red]];

    /**
     * @param list<\DateTimeImmutable> $milestones
     */
    public function __construct(
        public array $milestones = [new \DateTimeImmutable('2020-01-01')],
    ) {
    }
}
