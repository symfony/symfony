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

class RepeatedConstraints
{
    #[Assert\Length(max: 10)]
    #[Assert\Length(min: 2, max: 50)]
    public string $name = '';

    #[Assert\Regex('/^a/')]
    #[Assert\Regex('/b$/')]
    public string $code = '';

    #[Assert\Time]
    #[Assert\Regex('/^0/')]
    public string $openingTime = '';

    #[Assert\Range(min: 5, max: 20)]
    #[Assert\Range(min: 1, max: 100)]
    public int $quantity = 5;

    #[Assert\GreaterThan(10)]
    #[Assert\Positive]
    #[Assert\LessThan(100)]
    #[Assert\LessThan(1000)]
    public float $price = 50.0;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 5)]
    #[Assert\LessThan(5)]
    public int $stars = 1;

    #[Assert\DivisibleBy(2)]
    #[Assert\DivisibleBy(3)]
    public int $batchSize = 6;

    /** @var list<string> */
    #[Assert\Count(min: 2)]
    #[Assert\Count(min: 1, max: 5)]
    #[Assert\NotBlank]
    public array $tags = [];

    #[Assert\Choice(choices: ['small', 'medium', 'large'])]
    #[Assert\Choice(choices: ['medium', 'large', 'huge'])]
    public string $size = 'medium';

    #[Assert\Choice(choices: ['small'])]
    #[Assert\Choice(choices: ['huge'])]
    public string $impossibleSize;

    #[Assert\Choice(choices: [Suit::Hearts, Suit::Spades])]
    #[Assert\Choice(choices: [Suit::Spades])]
    public ?Suit $suit = null;

    #[Assert\Choice(choices: [Color::Green])]
    public ?Color $color = null;

    #[Assert\Choice(choices: ['small', 'large'])]
    #[Assert\NotNull]
    #[Assert\Choice(choices: ['large', 'huge'])]
    public ?string $requiredSize = null;

    /** @var list<string> */
    #[Assert\Choice(choices: ['red', 'green', 'blue'], multiple: true, max: 3)]
    #[Assert\Choice(choices: ['green', 'blue', 'black'], multiple: true, min: 1, max: 5)]
    #[Assert\NotBlank]
    public array $colors = [];
}
