<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Tests\ProfileData\Util;

use Symfony\Component\Profiler\ProfileData\Util\ValueExporter;

/**
 * ValueExporterTest.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class ValueExporterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ValueExporter
     */
    private $valueExporter;

    protected function setUp()
    {
        $this->valueExporter = new ValueExporter();
    }

    public function testNull()
    {
        $this->assertSame('null', $this->valueExporter->exportValue(null));
    }

    public function testBoolean()
    {
        $this->assertSame('false', $this->valueExporter->exportValue(false));
        $this->assertSame('true', $this->valueExporter->exportValue(true));
    }

    public function testArray()
    {
        $this->assertSame('[]', $this->valueExporter->exportValue(array()));
        $this->assertSame('[0 => 1, 1 => 2, 2 => 3]', $this->valueExporter->exportValue(array(1, 2, 3)));
        $deepArray = "[\n  0 => [\n    0 => 2\n  ]\n]";
        $this->assertSame($deepArray, $this->valueExporter->exportValue(array(array(2))));
    }

    public function testDateTime()
    {
        $dateTime = new \DateTime('2014-06-10 07:35:40', new \DateTimeZone('UTC'));
        $this->assertSame('Object(DateTime) - 2014-06-10T07:35:40+0000', $this->valueExporter->exportValue($dateTime));
    }

    public function testDateTimeImmutable()
    {
        if (!class_exists('DateTimeImmutable', false)) {
            $this->markTestSkipped('Test skipped, class DateTimeImmutable does not exist.');
        }

        $dateTime = new \DateTimeImmutable('2014-06-10 07:35:40', new \DateTimeZone('UTC'));
        $this->assertSame('Object(DateTimeImmutable) - 2014-06-10T07:35:40+0000', $this->valueExporter->exportValue($dateTime));
    }
}
