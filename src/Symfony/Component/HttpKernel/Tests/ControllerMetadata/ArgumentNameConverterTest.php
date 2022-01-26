<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\ControllerMetadata;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactoryInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentNameConverter;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ArgumentNameConverterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider getArgumentTests
     */
    public function testGetControllerArguments(array $resolvedArguments, array $argumentMetadatas, array $requestAttributes, array $expectedArguments)
    {
        $metadataFactory = $this->getMockBuilder(ArgumentMetadataFactoryInterface::class)->getMock();
        $metadataFactory->expects($this->any())
            ->method('createArgumentMetadata')
            ->willReturn($argumentMetadatas);

        $request = new Request();
        $request->attributes->add($requestAttributes);

        $converter = new ArgumentNameConverter($metadataFactory);
        $event = new ControllerArgumentsEvent($this->getMockBuilder(HttpKernelInterface::class)->getMock(), function () {
            return new Response();
        }, $resolvedArguments, $request, null);
        $actualArguments = $converter->getControllerArguments($event);
        $this->assertSame($expectedArguments, $actualArguments);
    }

    public function getArgumentTests()
    {
        // everything empty
        yield [[], [], [], []];

        // uses request attributes
        yield [[], [], ['post' => 5], ['post' => 5]];

        // resolves argument names correctly
        $arg1Metadata = new ArgumentMetadata('arg1Name', 'string', false, false, null);
        $arg2Metadata = new ArgumentMetadata('arg2Name', 'string', false, false, null);
        yield [['arg1Value', 'arg2Value'], [$arg1Metadata, $arg2Metadata], ['post' => 5], ['post' => 5, 'arg1Name' => 'arg1Value', 'arg2Name' => 'arg2Value']];

        // argument names have priority over request attributes
        yield [['arg1Value', 'arg2Value'], [$arg1Metadata, $arg2Metadata], ['arg1Name' => 'differentValue'], ['arg1Name' => 'arg1Value', 'arg2Name' => 'arg2Value']];

        // variadic arguments are resolved correctly
        $arg1Metadata = new ArgumentMetadata('arg1Name', 'string', false, false, null);
        $arg2VariadicMetadata = new ArgumentMetadata('arg2Name', 'string', true, false, null);
        yield [['arg1Value', 'arg2Value', 'arg3Value'], [$arg1Metadata, $arg2VariadicMetadata], [], ['arg1Name' => 'arg1Value', 'arg2Name' => ['arg2Value', 'arg3Value']]];

        // variadic argument receives no arguments, so becomes an empty array
        yield [['arg1Value'], [$arg1Metadata, $arg2VariadicMetadata], [], ['arg1Name' => 'arg1Value', 'arg2Name' => []]];

        // resolves nullable argument correctly
        $arg1Metadata = new ArgumentMetadata('arg1Name', 'string', false, false, null);
        $arg2NullableMetadata = new ArgumentMetadata('arg2Name', 'string', false, false, true);
        yield [['arg1Value', null], [$arg1Metadata, $arg2Metadata], ['post' => 5], ['post' => 5, 'arg1Name' => 'arg1Value', 'arg2Name' => null]];
    }
}
