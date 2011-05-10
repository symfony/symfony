<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\Extension\Core\DataTransformer\FileToStringTransformer;
use Symfony\Component\HttpFoundation\File\File;

class FileToStringTransformerTest extends \PHPUnit_Framework_TestCase
{
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new FileToStringTransformer();
    }

    public function testTransform()
    {
        $path = realpath(__DIR__.'/../../../Fixtures/foo');

        $file = new File($path);
        $t = $this->transformer->transform($file);

        $this->assertTrue(file_exists($path));
        $this->assertInternalType('string', $t);
        $this->assertEquals($path, realpath($t));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testTransformRequiresAFile()
    {
        $this->transformer->transform(array());
    }

    public function testReverseTransform()
    {
        $path = realpath(__DIR__.'/../../../Fixtures/foo');

        $file = new File($path);
        $r = $this->transformer->reverseTransform($path);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\File\File', $file);
        $this->assertEquals($path, realpath($r->getPath()));

    }

    /**
     * @expectedException Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformRequiresArray()
    {
        $t = $this->transformer->reverseTransform(__DIR__.'/../../../Fixtures/no-foo');
    }
}
