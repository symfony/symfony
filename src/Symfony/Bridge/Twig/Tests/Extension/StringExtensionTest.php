<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\StringRuntime;

class StringExtensionTest extends TestCase
{
    /**
     * @testWith [["partitions"], "fr", false, "partition"]
     *           ["partitions", "fr", true, "partition"]
     *           [["persons", "people"], "en", false, "person"]
     *           ["persons", "en", true, "person"]
     */
    public function testPluralize(array|string $expected, string $lang, bool $singleResult, string $value)
    {
        $extensionRuntime = new StringRuntime();
        $this->assertSame($expected, $extensionRuntime->pluralize($value, $lang, $singleResult));
    }

    /**
     * @testWith [["partition"], "fr", false, "partitions"]
     *           ["partition", "fr", true, "partitions"]
     *           [["person"], "en", false, "persons"]
     *           ["person", "en", true, "persons"]
     *           [["person"], "en", false, "people"]
     *           ["person", "en", true, "people"]
     */
    public function testSingularize(array|string $expected, string $lang, bool $singleResult, string $value)
    {
        $extensionRuntime = new StringRuntime();
        $this->assertSame($expected, $extensionRuntime->singularize($value, $lang, $singleResult));
    }

    /**
     * @testWith [["partitions"], "it", false, "partition"]
     *           [["partitions"], "it", true, "partition"]
     */
    public function testPluralizeInvalidLang(array|string $expected, string $lang, bool $singleResult, string $value)
    {
        $extensionRuntime = new StringRuntime();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Language "it" is not supported.');
        $this->assertSame($expected, $extensionRuntime->pluralize($value, $lang, $singleResult));
    }

    /**
     * @testWith [["partition"], "it", false, "partitions"]
     *           [["partition"], "it", true, "partitions"]
     */
    public function testSingularizeInvalidLang(array|string $expected, string $lang, bool $singleResult, string $value)
    {
        $extensionRuntime = new StringRuntime();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Language "it" is not supported.');
        $this->assertSame($expected, $extensionRuntime->singularize($value, $lang, $singleResult));
    }
}
