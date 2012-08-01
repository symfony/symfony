<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests;

use Symfony\Component\Finder\Expression\Expression;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class RegexTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getHasFlagsData
     */
    public function testHasFlags($regex, $start, $end)
    {
        $expr = new Expression($regex);

        $this->assertEquals($start, $expr->hasStartFlag());
        $this->assertEquals($end,   $expr->hasEndFlag());
    }

    /**
     * @dataProvider getHasJokersData
     */
    public function testHasJokers($regex, $start, $end)
    {
        $expr = new Expression($regex);

        $this->assertEquals($start, $expr->hasStartJoker());
        $this->assertEquals($end,   $expr->hasEndJoker());
    }

    /**
     * @dataProvider getSetFlagsData
     */
    public function testSetFlags($regex, $start, $end, $expected)
    {
        $expr = new Expression($regex);
        $expr->setStartFlag($start)->setEndFlag($end);

        $this->assertEquals($expected, $expr->render());
    }

    /**
     * @dataProvider getSetJokersData
     */
    public function testSetJokers($regex, $start, $end, $expected)
    {
        $expr = new Expression($regex);
        $expr->setStartJoker($start)->setEndJoker($end);

        $this->assertEquals($expected, $expr->render());
    }

    public function testOptions()
    {
        $expr = new Expression('~abc~is');
        $expr->removeOption('i')->addOption('m');

        $this->assertEquals('~abc~sm', $expr->render());
    }

    public function testMixFlagsAndJokers()
    {
        $expr = new Expression('~^.*abc.*$~is');

        $expr->setStartFlag(false)->setEndFlag(false)->setStartJoker(false)->setEndJoker(false);
        $this->assertEquals('~abc~is', $expr->render());

        $expr->setStartFlag(true)->setEndFlag(true)->setStartJoker(true)->setEndJoker(true);
        $this->assertEquals('~^.*abc.*$~is', $expr->render());
    }

    public function getHasFlagsData()
    {
        return array(
            array('~^abc~', true, false),
            array('~abc$~', false, true),
            array('~abc~', false, false),
            array('~^abc$~', true, true),
            array('~^abc\\$~', true, false),
        );
    }

    public function getHasJokersData()
    {
        return array(
            array('~.*abc~', true, false),
            array('~abc.*~', false, true),
            array('~abc~', false, false),
            array('~.*abc.*~', true, true),
            array('~.*abc\\.*~', true, false),
        );
    }

    public function getSetFlagsData()
    {
        return array(
            array('~abc~', true, false, '~^abc~'),
            array('~abc~', false, true, '~abc$~'),
            array('~abc~', false, false, '~abc~'),
            array('~abc~', true, true, '~^abc$~'),
        );
    }

    public function getSetJokersData()
    {
        return array(
            array('~abc~', true, false, '~.*abc~'),
            array('~abc~', false, true, '~abc.*~'),
            array('~abc~', false, false, '~abc~'),
            array('~abc~', true, true, '~.*abc.*~'),
        );
    }
}
