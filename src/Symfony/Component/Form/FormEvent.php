<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormEvent extends Event
{
    private FormInterface $form;
    protected $data;

    public function __construct(FormInterface $form, mixed $data)
    {
        $this->form = $form;
        $this->data = $data;
    }

    /**
     * Returns the form at the source of the event.
     */
    public function getForm(): FormInterface
    {
        return $this->form;
    }

    /**
     * Returns the data associated with this event.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Allows updating with some filtered data.
     *
     * @return void
     */
    public function setData(mixed $data)
    {
        $this->data = $data;
    }
}
