<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ParamConverter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\ParamConverter;
use Symfony\Component\HttpKernel\Controller\ParamConverter\DateTimeParamConverter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Tests\Fixtures\FooDateTime;

class DateTimeParamConverterTest extends TestCase
{
    private $converter;

    protected function setUp(): void
    {
        $this->converter = new DateTimeParamConverter();
    }

    public function testSupports()
    {
        $config = $this->createConfiguration(\DateTime::class);
        $this->assertTrue($this->converter->supports($config));

        $config = $this->createConfiguration(FooDateTime::class);
        $this->assertTrue($this->converter->supports($config));

        $config = $this->createConfiguration(__CLASS__);
        $this->assertFalse($this->converter->supports($config));

        $config = $this->createConfiguration();
        $this->assertFalse($this->converter->supports($config));
    }

    public function testApply()
    {
        $request = new Request([], [], ['start' => '2012-07-21 00:00:00']);
        $config = $this->createConfiguration('DateTime', 'start');

        $this->converter->apply($request, $config);

        $this->assertInstanceOf('DateTime', $request->attributes->get('start'));
        $this->assertEquals('2012-07-21', $request->attributes->get('start')->format('Y-m-d'));
    }

    public function testApplyUnixTimestamp()
    {
        $request = new Request([], [], ['start' => '989541720']);
        $config = $this->createConfiguration('DateTime', 'start');

        $this->converter->apply($request, $config);

        $this->assertInstanceOf('DateTime', $request->attributes->get('start'));
        $this->assertEquals('2001-05-11', $request->attributes->get('start')->format('Y-m-d'));
    }

    public function testApplyInvalidDate404Exception()
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Invalid date given for parameter "start".');

        $request = new Request([], [], ['start' => 'Invalid DateTime Format']);
        $config = $this->createConfiguration('DateTime', 'start');

        $this->converter->apply($request, $config);
    }

    public function testApplyWithFormatInvalidDate404Exception()
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Invalid date given for parameter "start".');

        $request = new Request([], [], ['start' => '2012-07-21']);
        $config = $this->createConfiguration('DateTime', 'start');
        $config->expects($this->any())->method('getOptions')->willReturn(['format' => 'd.m.Y']);

        $this->converter->apply($request, $config);
    }

    public function testApplyWithYmdFormatInvalidDate404Exception()
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Invalid date given for parameter "start".');

        $request = new Request([], [], ['start' => '2012-21-07']);
        $config = $this->createConfiguration('DateTime', 'start');
        $config->expects($this->any())->method('getOptions')->willReturn(['format' => 'Y-m-d']);

        $this->converter->apply($request, $config);
    }

    public function testApplyOptionalWithEmptyAttribute()
    {
        $request = new Request([], [], ['start' => '']);
        $config = $this->createConfiguration('DateTime', 'start');
        $config->expects($this->once())
            ->method('isOptional')
            ->willReturn(true);

        $this->assertTrue($this->converter->apply($request, $config));
        $this->assertNull($request->attributes->get('start'));
    }

    public function testApplyCustomClass()
    {
        $request = new Request([], [], ['start' => '2016-09-08 00:00:00']);
        $config = $this->createConfiguration(FooDateTime::class, 'start');

        $this->converter->apply($request, $config);

        $this->assertInstanceOf(FooDateTime::class, $request->attributes->get('start'));
        $this->assertEquals('2016-09-08', $request->attributes->get('start')->format('Y-m-d'));
    }

    /**
     * @requires PHP 5.5
     */
    public function testApplyDateTimeImmutable()
    {
        $request = new Request([], [], ['start' => '2016-09-08 00:00:00']);
        $config = $this->createConfiguration(\DateTimeImmutable::class, 'start');

        $this->converter->apply($request, $config);

        $this->assertInstanceOf(\DateTimeImmutable::class, $request->attributes->get('start'));
        $this->assertEquals('2016-09-08', $request->attributes->get('start')->format('Y-m-d'));
    }

    public function createConfiguration($class = null, $name = null)
    {
        $config = $this
            ->getMockBuilder(ParamConverter::class)
            ->setMethods(['getClass', 'getAliasName', 'getOptions', 'getName', 'allowArray', 'isOptional'])
            ->disableOriginalConstructor()
            ->getMock();

        if (null !== $name) {
            $config->expects($this->any())
                   ->method('getName')
                   ->willReturn($name);
        }
        if (null !== $class) {
            $config->expects($this->any())
                   ->method('getClass')
                   ->willReturn($class);
        }

        return $config;
    }
}
