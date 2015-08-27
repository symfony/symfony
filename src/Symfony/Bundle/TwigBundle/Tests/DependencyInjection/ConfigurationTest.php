<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\DependencyInjection;

use Symfony\Bundle\TwigBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    public function testDoNoDuplicateDefaultFormResources()
    {
        $input = array(
            'form_themes' => array('form_div_layout.html.twig'),
        );

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), array($input));

        $this->assertEquals(array('form_div_layout.html.twig'), $config['form_themes']);
    }
}
