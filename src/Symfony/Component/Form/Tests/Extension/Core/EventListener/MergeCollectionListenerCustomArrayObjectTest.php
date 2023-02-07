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

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\Tests\Fixtures\CustomArrayObject;

class MergeCollectionListenerCustomArrayObjectTest extends MergeCollectionListenerTestCase
{
    protected function getData(array $data)
    {
        return new CustomArrayObject($data);
    }

    protected function getBuilder($name = 'name')
    {
        return new FormBuilder($name, 'Symfony\Component\Form\Tests\Fixtures\CustomArrayObject', new EventDispatcher(), (new FormFactoryBuilder())->getFormFactory());
    }
}
