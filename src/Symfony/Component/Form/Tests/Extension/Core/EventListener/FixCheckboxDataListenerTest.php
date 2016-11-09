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

use Symfony\Component\Form\Extension\Core\EventListener\FixCheckboxDataListener;
use Symfony\Component\Form\FormEvent;

class FixCheckboxDataListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider valuesProvider
     */
    public function testFixCheckbox($data, $expexted)
    {
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $event = new FormEvent($form, $data);

        $listener = new FixCheckboxDataListener();
        $listener->preSubmit($event);

        $this->assertEquals($expexted, $event->getData());
    }

    public function valuesProvider()
    {
        return array(
            array('0', null),
            array('', ''),
            array('1', true),
        );
    }
}
