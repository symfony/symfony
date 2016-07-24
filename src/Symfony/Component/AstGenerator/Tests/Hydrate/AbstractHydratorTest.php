<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Tests\Hydrate;

use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Hydrate\ArrayHydrateGenerator;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

abstract class AbstractHydratorTest extends \PHPUnit_Framework_TestCase
{
    /** @var Standard */
    protected $printer;

    protected function setUp()
    {
        $this->printer = new Standard();
    }

    protected function getPropertyInfoExtractor($inputClass)
    {
        $propertyInfoExtractor = $this->getMockBuilder(PropertyInfoExtractorInterface::class)->getMock();
        $propertyInfoExtractor
            ->expects($this->any())
            ->method('getProperties')
            ->with($inputClass, $this->isType('array'))
            ->willReturn(['foo', 'bar']);
        $propertyInfoExtractor
            ->expects($this->any())
            ->method('isReadable')
            ->with($inputClass, $this->logicalOr('foo', 'bar'), $this->isType('array'))
            ->will($this->returnCallback(function ($class, $property) {
                return 'foo' === $property;
            }));
        $propertyInfoExtractor
            ->expects($this->any())
            ->method('isWritable')
            ->with($inputClass, $this->logicalOr('foo', 'bar'), $this->isType('array'))
            ->will($this->returnCallback(function ($class, $property) {
                return 'bar' === $property;
            }));
        $propertyInfoExtractor
            ->expects($this->any())
            ->method('getTypes')
            ->with($inputClass, $this->logicalOr('foo', 'bar'), $this->isType('array'))
            ->willReturn([new Type('string')]);

        return $propertyInfoExtractor;
    }
}
