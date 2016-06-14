<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToSignedTransformer;

class ValueToSignedTransformerTest extends \PHPUnit_Framework_TestCase
{
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new ValueToSignedTransformer('data', 'signature', 'ThisIsSecret', 'sha512');
    }

    protected function tearDown()
    {
        $this->transformer = null;
    }

    public function testTransform()
    {
        $output = array(
            'data' => 'foobar',
            'signature' => 'e643b257dce7856e96027a0df2e58fa91d9a3d01517a3010af7adcc212aa286eb1e13ad62c441367c8a55f7970e078d1998dfbeab6ebf1d80990e27cd98cb81c',
        );

        $this->assertSame($output, $this->transformer->transform('foobar'));
    }

    public function testReverseTransform()
    {
        $input = array(
            'data' => 'foobar',
            'signature' => 'e643b257dce7856e96027a0df2e58fa91d9a3d01517a3010af7adcc212aa286eb1e13ad62c441367c8a55f7970e078d1998dfbeab6ebf1d80990e27cd98cb81c',
        );

        $this->assertSame('foobar', $this->transformer->reverseTransform($input));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformCheckSignature()
    {
        $input = array(
            'data' => 'foobar2',
            'signature' => 'e643b257dce7856e96027a0df2e58fa91d9a3d01517a3010af7adcc212aa286eb1e13ad62c441367c8a55f7970e078d1998dfbeab6ebf1d80990e27cd98cb81c',
        );

        $this->transformer->reverseTransform($input);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformExpectArray()
    {
        $this->transformer->reverseTransform('foobar2');
    }
}