<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Form;

use Symfony\Bundle\FrameworkBundle\Form\PhpEngineThemeFactory;

class PhpEngineThemeFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $engine = $this->getMockBuilder('Symfony\Component\Templating\PhpEngine')
            ->disableOriginalConstructor()
            ->getMock();

        $factory = new PhpEngineThemeFactory($engine);

        $this->assertInstanceOf('Symfony\Bundle\FrameworkBundle\Form\PhpEngineTheme', $factory->create());
    }
}