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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Data\Bundle\Reader\IntlBundleReader;
use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[RequiresPhpExtension('intl')]
class IntlBundleReaderTest extends TestCase
{
    private IntlBundleReader $reader;

    protected function setUp(): void
    {
        $this->reader = new IntlBundleReader();
    }

    public function testReadReturnsArrayAccess(): void
    {
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'ro');

        $this->assertInstanceOf(\ArrayAccess::class, $data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadFollowsAlias(): void
    {
        // "alias" = "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'alias');

        $this->assertInstanceOf(\ArrayAccess::class, $data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadDoesNotFollowFallback(): void
    {
        // "ro_MD" -> "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'ro_MD');

        $this->assertInstanceOf(\ArrayAccess::class, $data);
        $this->assertSame('Bam', $data['Baz']);
        $this->assertArrayNotHasKey('Foo', $data);
        $this->assertNull($data['Foo']);
        $this->assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadDoesNotFollowFallbackAlias(): void
    {
        // "mo" = "ro_MD" -> "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'mo');

        $this->assertInstanceOf(\ArrayAccess::class, $data);
        $this->assertSame('Bam', $data['Baz'], 'data from the aliased locale can be accessed');
        $this->assertArrayNotHasKey('Foo', $data);
        $this->assertNull($data['Foo']);
        $this->assertArrayNotHasKey('ExistsNot', $data);
    }

    public function testReadFailsIfNonExistingLocale(): void
    {
        $this->expectException(ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/res', 'foo');
    }

    public function testReadFailsIfNonExistingFallbackLocale(): void
    {
        $this->expectException(ResourceBundleNotFoundException::class);
        $this->reader->read(__DIR__.'/Fixtures/res', 'ro_AT');
    }

    public function testReadFailsIfNonExistingDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->reader->read(__DIR__.'/foo', 'ro');
    }
}
