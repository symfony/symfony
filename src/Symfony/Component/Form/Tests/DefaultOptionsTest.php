<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\DefaultOptions;
use Symfony\Component\Form\Options;

class DefaultOptionsTest extends \PHPUnit_Framework_TestCase
{
    private $options;

    protected function setUp()
    {
        $this->options = new DefaultOptions();
    }

    public function testResolve()
    {
        $this->options->add(array(
            'foo' => 'bar',
            'bam' => function (Options $options) {
                return 'baz';
            },
        ));

        $result = array(
            'foo' => 'fee',
            'bam' => 'baz',
        );

        $this->assertEquals($result, $this->options->resolve(array(
            'foo' => 'fee',
        )));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidOptionException
     */
    public function testResolveFailsIfNonExistingOption()
    {
        $this->options->add(array(
            'foo' => 'bar',
        ));

        $this->options->resolve(array(
            'non_existing' => 'option',
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidOptionException
     */
    public function testResolveFailsIfOptionValueNotAllowed()
    {
        $this->options->add(array(
            'foo' => 'bar',
        ));

        $this->options->addAllowedValues(array(
            'foo' => array('bar', 'baz'),
        ));

        $this->options->resolve(array(
            'foo' => 'bam',
        ));
    }
}
