<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

/**

 */
class ParentDummy
{
    /**
     * Short description.
     *
     * Long description.
     */
    public $foo;

    /**
     * @var float
     */
    public $foo2;

    /**
     * @var callable
     */
    public $foo3;

    /**
     * @var void
     */
    public $foo4;

    /**
     * @var mixed
     */
    public $foo5;

    /**
     * @var \SplFileInfo[]|resource
     */
    public $files;

    /**
     * @var static
     */
    public $propertyTypeStatic;

    /**
     * @var parent
     */
    public $parentAnnotationNoParent;

    /**
     * @return bool|null
     */
    public function isC()
    {
    }

    /**
     * @return true|null
     */
    public function isCt()
    {
    }

    /**
     * @return false|null
     */
    public function isCf()
    {
    }

    /**
     * @return bool
     */
    public function canD()
    {
    }

    /**
     * @return true
     */
    public function canDt()
    {
    }

    /**
     * @return false
     */
    public function canDf()
    {
    }

    /**
     * @param resource $e
     */
    public function addE($e)
    {
    }

    /**
     * @param \DateTime $f
     */
    public function removeF(\DateTime $f)
    {
    }
}
