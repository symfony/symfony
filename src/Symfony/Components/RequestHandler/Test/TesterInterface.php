<?php

namespace Symfony\Components\RequestHandler\Test;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * TesterInterface.
 *
 * @package    Symfony
 * @subpackage Components_RequestHandler
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface TesterInterface
{
  /**
   * Sets the TestCase instance associated with this tester object.
   *
   * @param \PHPUnit_Framework_TestCase $test A \PHPUnit_Framework_TestCase instance
   */
  public function setTestCase(\PHPUnit_Framework_TestCase $test);
}
