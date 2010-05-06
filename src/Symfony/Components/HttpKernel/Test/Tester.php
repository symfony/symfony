<?php

namespace Symfony\Components\HttpKernel\Test;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Tester is the base class for all tester classes.
 *
 * @package    Symfony
 * @subpackage Components_HttpKernel
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Tester implements TesterInterface
{
    protected $test;

    /**
     * Sets the TestCase instance associated with this tester object.
     *
     * @param \PHPUnit_Framework_TestCase $test A \PHPUnit_Framework_TestCase instance
     */
    public function setTestCase(\PHPUnit_Framework_TestCase $test)
    {
        $this->test = $test;
    }
}
