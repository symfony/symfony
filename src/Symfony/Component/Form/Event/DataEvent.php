<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Form\FormInterface;

class DataEvent extends Event
{
    private $form;
    protected $data;

    /**
     * Constructs an event.
     *
     * @param FormInterface $form The associated form
     * @param mixed         $data The data
     */
    public function __construct(FormInterface $form, $data)
    {
        $this->form = $form;
        $this->data = $data;
    }

    /**
     * Returns the form at the source of the event.
     *
     * @return FormInterface
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Returns the data associated with this event.
     *
     * @return type
     */
    public function getData()
    {
        return $this->data;
    }
}
