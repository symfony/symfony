<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\PropertyInfo\Tests;

use Symphony\Component\Cache\Adapter\ArrayAdapter;
use Symphony\Component\PropertyInfo\PropertyInfoCacheExtractor;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoCacheExtractorTest extends AbstractPropertyInfoExtractorTest
{
    protected function setUp()
    {
        parent::setUp();

        $this->propertyInfo = new PropertyInfoCacheExtractor($this->propertyInfo, new ArrayAdapter());
    }

    public function testGetShortDescription()
    {
        parent::testGetShortDescription();
        parent::testGetShortDescription();
    }

    public function testGetLongDescription()
    {
        parent::testGetLongDescription();
        parent::testGetLongDescription();
    }

    public function testGetTypes()
    {
        parent::testGetTypes();
        parent::testGetTypes();
    }

    public function testIsReadable()
    {
        parent::testIsReadable();
        parent::testIsReadable();
    }

    public function testIsWritable()
    {
        parent::testIsWritable();
        parent::testIsWritable();
    }

    public function testGetProperties()
    {
        parent::testGetProperties();
        parent::testGetProperties();
    }
}
