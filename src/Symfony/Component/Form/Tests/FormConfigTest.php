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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
use Symfony\Component\Form\FormConfigBuilder;

class FormConfigTest extends \PHPUnit_Framework_TestCase
{
    public function getHtml5Ids()
    {
        return array(
            array('z0', true),
            array('A0', true),
            array('A9', true),
            array('Z0', true),
            array('#', true),
            array('a#', true),
            array('a$', true),
            array('a%', true),
            array('a ', false),
            array("a\t", false),
            array("a\r", false),
            array("a\n", false),
            array("a\f", false),
            array('a-', true),
            array('a_', true),
            array('a:', true),
            array('0', true),
            array('9', true),
            array('_', true),
            // Brackets and periods are allowed by the HTML5 spec, but disallowed by us
            // because they break the generated property paths
            array('a[a]', false),
            array('a[', false),
            array('a]', false),
            array('a.', false),
            // Integers are allowed
            array(0, true),
            array(123, true),
            array(-1, true),
            array(-123, true),
            // NULL is allowed
            array(null, true),
            // Other types are not
            array(1.23, false),
            array(5., false),
            array(true, false),
            array(new \stdClass(), false),
        );
    }

    /**
     * @dataProvider getHtml5Ids
     */
    public function testNameAcceptsOnlyNamesValidAsIdsInHtml5($name, $accepted)
    {
        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        try {
            new FormConfigBuilder($name, null, $dispatcher);
            if (!$accepted) {
                $this->fail(sprintf('The value "%s" should not be accepted', $name));
            }
        } catch (UnexpectedTypeException $e) {
            // if the value was not accepted, but should be, rethrow exception
            if ($accepted) {
                throw $e;
            }
        } catch (\InvalidArgumentException $e) {
            // if the value was not accepted, but should be, rethrow exception
            if ($accepted) {
                throw $e;
            }
        }
    }
}
