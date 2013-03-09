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
use Symfony\Component\Form\FormEvent;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.1, to be removed in 2.3. Code against
 *             {@link \Symfony\Component\Form\FormEvent} instead.
 */
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
        if (!$this instanceof FormEvent) {
            trigger_error(sprintf('%s is deprecated since version 2.1 and will be removed in 2.3. Code against \Symfony\Component\Form\FormEvent instead.', get_class($this)), E_USER_DEPRECATED);
        }

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
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Allows updating with some filtered data.
     *
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }
}
