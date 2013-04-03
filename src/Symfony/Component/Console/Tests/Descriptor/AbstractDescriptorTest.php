<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Descriptor;

use Symfony\Component\Console\Descriptor\DescriptorInterface;
use Symfony\Component\Console\Input\InputArgument;

abstract class AbstractDescriptorTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getDescribeTestData */
    public function testDescribe(DescriptorInterface $descriptor, $object, $description)
    {
        $this->assertTrue($descriptor->supports($object));
        $this->assertEquals(trim($description), trim($descriptor->describe($object)));
    }

    public function getDescribeTestData()
    {
        $data = array();
        $descriptor = $this->getDescriptor();

        foreach ($this->getObjects() as $name => $object) {
            $description = file_get_contents(sprintf('%s/../Fixtures/%s.%s', __DIR__, $name, $descriptor->getFormat()));
            $data[] = array($descriptor, $object, $description);
        }

        return $data;
    }

    abstract protected function getDescriptor();
    abstract protected function getObjects();
}
