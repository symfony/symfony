<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsParser\Tests;

use Symfony\Component\OptionsParser\OptionsParser;
use Symfony\Component\OptionsParser\Options;

class OptionsParserTest extends \PHPUnit_Framework_TestCase
{
    private $options;

    protected function setUp()
    {
        $this->parser = new OptionsParser();
    }

    public function testParse()
    {
        $this->parser->setDefaults(array(
            'one' => '1',
            'two' => '2',
        ));

        $options = array(
            'two' => '20',
        );

        $this->assertEquals(array(
            'one' => '1',
            'two' => '20',
        ), $this->parser->parse($options));
    }

    public function testParseLazy()
    {
        $this->parser->setDefaults(array(
            'one' => '1',
            'two' => function (Options $options) {
                return '20';
            },
        ));

        $this->assertEquals(array(
            'one' => '1',
            'two' => '20',
        ), $this->parser->parse(array()));
    }

    public function testParseLazyDependencyOnOptional()
    {
        $this->parser->setDefaults(array(
            'one' => '1',
            'two' => function (Options $options) {
                return $options['one'] . '2';
            },
        ));

        $options = array(
            'one' => '10',
        );

        $this->assertEquals(array(
            'one' => '10',
            'two' => '102',
        ), $this->parser->parse($options));
    }

    public function testParseLazyDependencyOnMissingOptionalWithoutDefault()
    {
        $test = $this;

        $this->parser->setOptional(array(
            'one',
        ));

        $this->parser->setDefaults(array(
            'two' => function (Options $options) use ($test) {
                $test->assertFalse(isset($options['one']));

                return '2';
            },
        ));

        $options = array(
        );

        $this->assertEquals(array(
            'two' => '2',
        ), $this->parser->parse($options));
    }

    public function testParseLazyDependencyOnOptionalWithoutDefault()
    {
        $test = $this;

        $this->parser->setOptional(array(
            'one',
        ));

        $this->parser->setDefaults(array(
            'two' => function (Options $options) use ($test) {
                $test->assertTrue(isset($options['one']));

                return $options['one'] . '2';
            },
        ));

        $options = array(
            'one' => '10',
        );

        $this->assertEquals(array(
            'one' => '10',
            'two' => '102',
        ), $this->parser->parse($options));
    }

    public function testParseLazyDependencyOnRequired()
    {
        $this->parser->setRequired(array(
            'one',
        ));
        $this->parser->setDefaults(array(
            'two' => function (Options $options) {
                return $options['one'] . '2';
            },
        ));

        $options = array(
            'one' => '10',
        );

        $this->assertEquals(array(
            'one' => '10',
            'two' => '102',
        ), $this->parser->parse($options));
    }

    /**
     * @expectedException Symfony\Component\OptionsParser\Exception\InvalidOptionsException
     */
    public function testParseFailsIfNonExistingOption()
    {
        $this->parser->setDefaults(array(
            'one' => '1',
        ));

        $this->parser->setRequired(array(
            'two',
        ));

        $this->parser->setOptional(array(
            'three',
        ));

        $this->parser->parse(array(
            'foo' => 'bar',
        ));
    }

    /**
     * @expectedException Symfony\Component\OptionsParser\Exception\MissingOptionsException
     */
    public function testParseFailsIfMissingRequiredOption()
    {
        $this->parser->setRequired(array(
            'one',
        ));

        $this->parser->setDefaults(array(
            'two' => '2',
        ));

        $this->parser->parse(array(
            'two' => '20',
        ));
    }

    public function testParseSucceedsIfOptionValueAllowed()
    {
        $this->parser->setDefaults(array(
            'one' => '1',
        ));

        $this->parser->setAllowedValues(array(
            'one' => array('1', 'one'),
        ));

        $options = array(
            'one' => 'one',
        );

        $this->assertEquals(array(
            'one' => 'one',
        ), $this->parser->parse($options));
    }

    public function testParseSucceedsIfOptionValueAllowed2()
    {
        $this->parser->setDefaults(array(
            'one' => '1',
            'two' => '2',
        ));

        $this->parser->addAllowedValues(array(
            'one' => array('1'),
            'two' => array('2'),
        ));
        $this->parser->addAllowedValues(array(
            'one' => array('one'),
            'two' => array('two'),
        ));

        $options = array(
            'one' => '1',
            'two' => 'two',
        );

        $this->assertEquals(array(
            'one' => '1',
            'two' => 'two',
        ), $this->parser->parse($options));
    }

    /**
     * @expectedException Symfony\Component\OptionsParser\Exception\InvalidOptionsException
     */
    public function testParseFailsIfOptionValueNotAllowed()
    {
        $this->parser->setDefaults(array(
            'one' => '1',
        ));

        $this->parser->setAllowedValues(array(
            'one' => array('1', 'one'),
        ));

        $this->parser->parse(array(
            'one' => '2',
        ));
    }

    /**
     * @expectedException Symfony\Component\OptionsParser\Exception\OptionDefinitionException
     */
    public function testSetRequiredFailsIfDefaultIsPassed()
    {
        $this->parser->setRequired(array(
            'one' => '1',
        ));
    }

    /**
     * @expectedException Symfony\Component\OptionsParser\Exception\OptionDefinitionException
     */
    public function testSetOptionalFailsIfDefaultIsPassed()
    {
        $this->parser->setOptional(array(
            'one' => '1',
        ));
    }
}
