<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;

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

    public function testIsInitializable()
    {
        parent::testIsInitializable();
        parent::testIsInitializable();
    }
}
