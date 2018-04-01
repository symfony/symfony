<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Core\EventListener;

use Symphony\Component\Form\Tests\Fixtures\CustomArrayObject;
use Symphony\Component\Form\FormBuilder;

class MergeCollectionListenerCustomArrayObjectTest extends MergeCollectionListenerTest
{
    protected function getData(array $data)
    {
        return new CustomArrayObject($data);
    }

    protected function getBuilder($name = 'name')
    {
        return new FormBuilder($name, 'Symphony\Component\Form\Tests\Fixtures\CustomArrayObject', $this->dispatcher, $this->factory);
    }
}
