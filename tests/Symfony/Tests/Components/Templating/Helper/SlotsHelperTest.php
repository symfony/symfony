<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Templating\Helper;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\Templating\Helper\SlotsHelper;

class SlotsHelperTest extends \PHPUnit_Framework_TestCase
{
  public function testHasGetSet()
  {
    $helper = new SlotsHelper();
    $helper->set('foo', 'bar');
    $this->assertEquals($helper->get('foo'), 'bar', '->set() sets a slot value');
    $this->assertEquals($helper->get('bar', 'bar'), 'bar', '->get() takes a default value to return if the slot does not exist');

    $this->assertTrue($helper->has('foo'), '->has() returns true if the slot exists');
    $this->assertTrue(!$helper->has('bar'), '->has() returns false if the slot does not exist');
  }

  public function testOutput()
  {
    $helper = new SlotsHelper();
    $helper->set('foo', 'bar');
    ob_start();
    $ret = $helper->output('foo');
    $output = ob_get_clean();
    $this->assertEquals($output, 'bar', '->output() outputs the content of a slot');
    $this->assertEquals($ret, true, '->output() returns true if the slot exists');

    ob_start();
    $ret = $helper->output('bar', 'bar');
    $output = ob_get_clean();
    $this->assertEquals($output, 'bar', '->output() takes a default value to return if the slot does not exist');
    $this->assertEquals($ret, true, '->output() returns true if the slot does not exist but a default value is provided');

    ob_start();
    $ret = $helper->output('bar');
    $output = ob_get_clean();
    $this->assertEquals($output, '', '->output() outputs nothing if the slot does not exist');
    $this->assertEquals($ret, false, '->output() returns false if the slot does not exist');
  }

  public function testStartStop()
  {
    $helper = new SlotsHelper();
    $helper->start('bar');
    echo 'foo';
    $helper->stop();
    $this->assertEquals($helper->get('bar'), 'foo', '->start() starts a slot');
    $this->assertTrue($helper->has('bar'), '->starts() starts a slot');

    $helper->start('bar');
    try
    {
      $helper->start('bar');
      $helper->stop();
      $this->fail('->start() throws an InvalidArgumentException if a slot with the same name is already started');
    }
    catch (\InvalidArgumentException $e)
    {
      $helper->stop();
    }

    try
    {
      $helper->stop();
      $this->fail('->stop() throws an LogicException if no slot is started');
    }
    catch (\LogicException $e)
    {
    }
  }
}
