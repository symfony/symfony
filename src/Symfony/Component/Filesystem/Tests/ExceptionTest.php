<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Tests;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Test class for Filesystem.
 */
class ExceptionTest extends \PHPUnit_Framework_TestCase
{

	public function testSetPath()
	{
		$e = new IOException('/foo');

		$reflection = new \ReflectionProperty($e, 'path');
		$reflection->setAccessible(true);

		$this->assertEquals('/foo', $reflection->getValue($e));	
	}

	public function testGetPath()
	{
		$e = new IOException('/foo');
		$this->assertEquals('/foo', $e->getPath());
	}

	public function testGeneratedMessage()
	{
		$e = new FileNotFoundException('/foo');
		$this->assertEquals('/foo', $e->getPath());
		$this->assertEquals('File "/foo" couldnot be found', $e->getMessage());
	}

	public function testCustomMessage()
	{
		$e = new FileNotFoundException('/foo', 'bar');
		$this->assertEquals('bar', $e->getMessage());
	}

}