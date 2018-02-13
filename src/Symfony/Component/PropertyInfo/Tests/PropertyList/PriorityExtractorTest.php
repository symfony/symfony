<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\PropertyList;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\PropertyList\PriorityExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummySecondExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\NullExtractor;

/**
 * @author Andrey Samusev <andrey_simfi@list.ru>
 */
class PriorityExtractorTest extends TestCase
{
    /**
     * @dataProvider provideGetProperties
     */
    public function testGetProperties($assert, $propertyList)
    {
        $this->assertEquals($assert, $propertyList->getProperties('Foo'));
    }

    public function provideGetProperties()
    {
        return array(
            'null properties' => array(null, new PriorityExtractor(array(new NullExtractor()))),
            'DummyExtractor properties' => array(array('a', 'b'), new PriorityExtractor(array(new DummyExtractor()))),
            'Dummy Second Extractor properties' => array(array('a', 'c', 'd'), new PriorityExtractor(array(new DummySecondExtractor(), new DummyExtractor()))),
            'All extractors properties' => array(array('a', 'b'), new PriorityExtractor(array(new NullExtractor(), new DummyExtractor(), new DummySecondExtractor()))),
        );
    }
}
