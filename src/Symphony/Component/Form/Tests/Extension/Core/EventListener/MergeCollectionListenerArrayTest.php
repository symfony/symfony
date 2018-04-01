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

use Symphony\Component\Form\FormBuilder;

class MergeCollectionListenerArrayTest extends MergeCollectionListenerTest
{
    protected function getData(array $data)
    {
        return $data;
    }

    protected function getBuilder($name = 'name')
    {
        return new FormBuilder($name, null, $this->dispatcher, $this->factory);
    }
}
