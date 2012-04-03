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

use Symfony\Component\Form\FormInterface;

class ChildDataEvent extends DataEvent
{
    private $name;

    /**
     * Constructs an event.
     *
     * @param FormInterface $form The associated form
     * @param string        $name The name of the field that was just set
     * @param mixed         $data The data of the field that was just set
     */
    public function __construct(FormInterface $form, $name, $data)
    {
        $this->name = $name;
        parent::__construct($form, $data);
    }

    /**
     * Gets the data of the field that was just set
     */
    public function getData()
    {
        return parent::getData();
    }

    /**
     * Gets the name of the field that was updated
     */
    public function getName()
    {
        return $this->name;
    }
}
