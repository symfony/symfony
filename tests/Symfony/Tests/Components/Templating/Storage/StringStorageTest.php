<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Templating\Storage;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\Templating\Storage\Storage;
use Symfony\Components\Templating\Storage\StringStorage;

class StringStorageTest extends \PHPUnit_Framework_TestCase
{
  public function testGetContent()
  {
    $storage = new StringStorage('foo');
    $this->assertTrue($storage instanceof Storage, 'StringStorage is an instance of Storage');
    $storage = new StringStorage('foo');
    $this->assertEquals('foo', $storage->getContent(), '->getContent() returns the content of the template');
  }
}
