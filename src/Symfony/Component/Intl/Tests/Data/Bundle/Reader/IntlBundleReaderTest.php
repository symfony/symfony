<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Data\Bundle\Reader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Data\Bundle\Reader\IntlBundleReader;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @requires extension intl
 */
class IntlBundleReaderTest extends TestCase
{
    /**
     * @var IntlBundleReader
     */
    private $reader;

    protected function setUp(): void
    {
        $this->reader = new IntlBundleReader();
    }

    public function testReadReturnsArrayAccess()
    {
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'ro');

        self::assertInstanceOf(\ArrayAccess::class, $data);
        self::assertSame('Bar', $data['Foo']);
        self::assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadFollowsAlias()
    {
        // "alias" = "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'alias');

        self::assertInstanceOf(\ArrayAccess::class, $data);
        self::assertSame('Bar', $data['Foo']);
        self::assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadDoesNotFollowFallback()
    {
        // "ro_MD" -> "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'ro_MD');

        self::assertInstanceOf(\ArrayAccess::class, $data);
        self::assertSame('Bam', $data['Baz']);
        self::assertArrayNotHasKey('Foo', $data);
        self::assertNull($data['Foo']);
        self::assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadDoesNotFollowFallbackAlias()
    {
        // "mo" = "ro_MD" -> "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'mo');

        self::assertInstanceOf(\ArrayAccess::class, $data);
        self::assertSame('Bam', $data['Baz'], 'data from the aliased locale can be accessed');
        self::assertArrayNotHasKey('Foo', $data);
        self::assertNull($data['Foo']);
        self::assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadFailsIfNonExistingLocale()
    {
        self::expectException(ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/res', 'foo');
    }

    public function testReadFailsIfNonExistingFallbackLocale()
    {
        self::expectException(ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/res', 'ro_AT');
    }

    public function testReadFailsIfNonExistingDirectory()
    {
        self::expectException(RuntimeException::class);
        $this->reader->read(__DIR__.'/foo', 'ro');
    }
}
