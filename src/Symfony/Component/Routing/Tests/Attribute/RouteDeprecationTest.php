<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Attribute;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\RouteDeprecation;
use Symfony\Component\Routing\Exception\InvalidArgumentException;

/**
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
class RouteDeprecationTest extends TestCase
{
    public function testToArray()
    {
        $deprecation = new RouteDeprecation('2026-10-01', new \DateTimeImmutable('2027-06-30 02:00:00', new \DateTimeZone('Europe/Paris')), 'https://example.com/deprecation');

        $this->assertSame([
            'since' => '2026-10-01T00:00:00+00:00',
            'sunset' => '2027-06-30T00:00:00+00:00',
            'link' => 'https://example.com/deprecation',
        ], $deprecation->toArray());
    }

    public function testToArrayOmitsUnsetValues()
    {
        $this->assertSame(['since' => '2026-10-01T00:00:00+00:00'], (new RouteDeprecation('2026-10-01'))->toArray());
    }

    public function testSunsetCanEqualSince()
    {
        $deprecation = new RouteDeprecation('2026-10-01', '2026-10-01');

        $this->assertEquals($deprecation->since, $deprecation->sunset);
    }

    public function testLinkCanContainSingleQuotes()
    {
        $this->assertSame("https://example.com/O'Reilly", (new RouteDeprecation('2026-10-01', link: "https://example.com/O'Reilly"))->link);
    }

    public function testFromArray()
    {
        $this->assertEquals(new RouteDeprecation('2026-10-01', '2027-06-30', 'https://example.com/deprecation'), RouteDeprecation::fromArray(['since' => '2026-10-01', 'sunset' => '2027-06-30', 'link' => 'https://example.com/deprecation']));
    }

    #[DataProvider('provideInvalidDeprecations')]
    public function testConstructorRejectsInvalidDeprecations(string $since, ?string $sunset, ?string $link, string $message)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new RouteDeprecation($since, $sunset, $link);
    }

    public static function provideInvalidDeprecations(): iterable
    {
        yield 'invalid date' => ['not a date', null, null, 'The "since" date of a route deprecation must be a valid date, "not a date" given.'];
        yield 'sunset before since' => ['2027-06-30', '2026-10-01', null, 'The "sunset" date of a route deprecation cannot be earlier than its "since" date.'];
        yield 'empty link' => ['2026-10-01', null, '', 'The "link" of a route deprecation must be a non-empty URI without "<", ">", double quotes or line breaks, "" given.'];
        yield 'link breaking the header' => ['2026-10-01', null, 'https://example.com>; rel="next"', 'The "link" of a route deprecation must be a non-empty URI without "<", ">", double quotes or line breaks, "https://example.com>; rel="next"" given.'];
        yield 'link with a line break' => ['2026-10-01', null, "https://example.com\r\nX-Foo: bar", 'The "link" of a route deprecation must be a non-empty URI without'];
    }

    #[DataProvider('provideInvalidArrays')]
    public function testFromArrayRejectsInvalidArrays(array $config, string $message)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        RouteDeprecation::fromArray($config);
    }

    public static function provideInvalidArrays(): iterable
    {
        yield 'missing since' => [['sunset' => '2027-06-30'], 'A route deprecation requires a "since" date.'];
        yield 'unknown key' => [['since' => '2026-10-01', 'message' => 'Use v2'], 'A route deprecation only accepts the "since", "sunset" and "link" keys, "message" given.'];
        yield 'invalid date type' => [['since' => '2026-10-01', 'sunset' => true], 'The "sunset" date of a route deprecation must be a string or a "DateTimeInterface", "bool" given.'];
        yield 'unquoted YAML date' => [['since' => 1790812800], 'The "since" date of a route deprecation must be a string or a "DateTimeInterface", "int" given. Did you forget to quote the date in YAML?'];
        yield 'invalid link type' => [['since' => '2026-10-01', 'link' => ['https://example.com']], 'The "link" of a route deprecation must be a string, "array" given.'];
    }
}
