<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\EventListener;

use Symfony\Component\Form\FormBuilder;

class MergeCollectionListenerArrayObjectTest extends MergeCollectionListenerTest
{
    protected function getData(array $data)
    {
        return new \ArrayObject($data);
    }

    protected function getBuilder($name = 'name')
    {
        return new FormBuilder($name, '\ArrayObject', $this->dispatcher, $this->factory);
    }
}
