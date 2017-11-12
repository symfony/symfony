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

    public function testReadReturnsArrayAccess(): void
    {
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'ro');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    public function testReadFollowsAlias(): void
    {
        // "alias" = "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'alias');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bar', $data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    public function testReadDoesNotFollowFallback(): void
    {
        // "ro_MD" -> "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'ro_MD');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bam', $data['Baz']);
        $this->assertFalse(isset($data['Foo']));
        $this->assertNull($data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    public function testReadDoesNotFollowFallbackAlias(): void
    {
        // "mo" = "ro_MD" -> "ro"
        $data = $this->reader->read(__DIR__.'/Fixtures/res', 'mo');

        $this->assertInstanceOf('\ArrayAccess', $data);
        $this->assertSame('Bam', $data['Baz'], 'data from the aliased locale can be accessed');
        $this->assertFalse(isset($data['Foo']));
        $this->assertNull($data['Foo']);
        $this->assertFalse(isset($data['ExistsNot']));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\ResourceBundleNotFoundException
     */
    public function testReadFailsIfNonExistingLocale(): void
    {
        $this->reader->read(__DIR__.'/Fixtures/res', 'foo');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\ResourceBundleNotFoundException
     */
    public function testReadFailsIfNonExistingFallbackLocale(): void
    {
        $this->reader->read(__DIR__.'/Fixtures/res', 'ro_AT');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\RuntimeException
     */
    public function testReadFailsIfNonExistingDirectory(): void
    {
        $this->reader->read(__DIR__.'/foo', 'ro');
    }
}
