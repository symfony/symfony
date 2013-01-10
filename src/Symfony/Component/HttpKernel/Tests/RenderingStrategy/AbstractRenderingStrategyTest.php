<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\RenderingStrategy;

abstract class AbstractRenderingStrategyTest extends \PHPUnit_Framework_TestCase
{
    protected function getUrlGenerator()
    {
        $generator = $this->getMock('Symfony\Component\Routing\Generator\UrlGeneratorInterface');
        $generator
            ->expects($this->any())
            ->method('generate')
            ->will($this->returnCallback(function ($name, $parameters, $referenceType) {
                return '/'.$parameters['_controller'].'.'.$parameters['_format'];
            }))
        ;

        return $generator;
    }
}
