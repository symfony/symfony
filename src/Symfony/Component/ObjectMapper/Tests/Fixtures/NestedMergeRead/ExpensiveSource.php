<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMergeRead;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: Target::class)]
class ExpensiveSource
{
    public static int $reads = 0;

    #[Map(target: 'label')]
    public string $name = 'foo';

    private ?array $report = null;
    private string $note = 'n';
    private Plain $plain;
    private OtherMapped $other;
    private Timestamp $timestamp;
    private AddressInterface $collection;
    private Plain $unset;
    private Suit $suit = Suit::Hearts;

    public function __construct()
    {
        $this->plain = new Plain();
        $this->other = new OtherMapped();
        $this->timestamp = new Timestamp();
        $this->collection = new Plain();
    }

    public function getSuit(): Suit
    {
        ++self::$reads;

        return $this->suit;
    }

    public function getReport(): array
    {
        ++self::$reads;

        return $this->report ??= ['expensive'];
    }

    public function getNote(): string
    {
        ++self::$reads;

        return $this->note;
    }

    public function getPlain(): Plain
    {
        ++self::$reads;

        return $this->plain;
    }

    public function getOther(): OtherMapped
    {
        ++self::$reads;

        return $this->other;
    }

    public function getTimestamp(): Timestamp
    {
        ++self::$reads;

        return $this->timestamp;
    }

    public function getCollection(): AddressInterface
    {
        ++self::$reads;

        return $this->collection;
    }

    public function getUnset(): Plain
    {
        ++self::$reads;

        return $this->unset;
    }
}
