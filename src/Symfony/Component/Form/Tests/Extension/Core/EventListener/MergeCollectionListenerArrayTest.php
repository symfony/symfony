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

class MergeCollectionListenerArrayTest extends MergeCollectionListenerTest
{
    protected function getData(array $data)
    {
        return $data;
    }
}
