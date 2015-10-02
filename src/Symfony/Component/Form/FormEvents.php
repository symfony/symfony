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

/**
 * To learn more about how form events work check the documentation
 * entry at {@link https://symfony.com/doc/any/components/form/form_events.html}.
 *
 * To learn how to dynamically modify forms using events check the cookbook
 * entry at {@link https://symfony.com/doc/any/cookbook/form/dynamic_form_modification.html}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class FormEvents
{
    /**
     * The PRE_SUBMIT event is dispatched at the beginning of the Form::submit() method.
     *
     * It can be used to:
     *  - Change data from the request, before submitting the data to the form.
     *  - Add or remove form fields, before submitting the data to the form.
     * The event listener method receives a Symfony\Component\Form\FormEvent instance.
     *
     * @Event
     */
    const PRE_SUBMIT = 'form.pre_bind';

    /**
     * The SUBMIT event is dispatched just before the Form::submit() method
     * transforms back the normalized data to the model and view data.
     *
     * It can be used to change data from the normalized representation of the data.
     * The event listener method receives a Symfony\Component\Form\FormEvent instance.
     *
     * @Event
     */
    const SUBMIT = 'form.bind';

    /**
     * The FormEvents::POST_SUBMIT event is dispatched after the Form::submit()
     * once the model and view data have been denormalized.
     *
     * It can be used to fetch data after denormalization.
     * The event listener method receives a Symfony\Component\Form\FormEvent instance.
     *
     * @Event
     */
    const POST_SUBMIT = 'form.post_bind';

    /**
     * The FormEvents::PRE_SET_DATA event is dispatched at the beginning of the Form::setData() method.
     *
     * It can be used to:
     *  - Modify the data given during pre-population;
     *  - Modify a form depending on the pre-populated data (adding or removing fields dynamically).
     * The event listener method receives a Symfony\Component\Form\FormEvent instance.
     *
     * @Event
     */
    const PRE_SET_DATA = 'form.pre_set_data';

    /**
     * The FormEvents::POST_SET_DATA event is dispatched at the end of the Form::setData() method.
     *
     * This event is mostly here for reading data after having pre-populated the form.
     * The event listener method receives a Symfony\Component\Form\FormEvent instance.
     *
     * @Event
     */
    const POST_SET_DATA = 'form.post_set_data';

    private function __construct()
    {
    }
}
