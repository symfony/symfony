<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\EventListener;

use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\EventListener\StripTagsListener;

class StripTagsListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testStripTags()
    {
        $data = "<div><strong>Foo!</strong>Bar!<span>Baz!</span></table></body>";
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $event = new FilterDataEvent($form, $data);

        $filter = new StripTagsListener();
        $filter->filterBoundClientData($event);

        $this->assertEquals('Foo!Bar!Baz!', $event->getData());
    }
}