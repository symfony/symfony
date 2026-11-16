<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Event\FormSubmitEvent;

/**
 * A container whose controls are submitted together, like an HTML form.
 *
 * It only dispatches the event: what collects the values and decides when
 * to submit (the HTML layer for <form>) calls submit().
 *
 * @experimental
 */
class FormWidget extends ContainerWidget
{
    /**
     * @param callable(FormSubmitEvent): void $callback
     *
     * @return $this
     */
    public function onSubmit(callable $callback): static
    {
        return $this->on(FormSubmitEvent::class, $callback);
    }

    /**
     * @param array<string, string|list<string>> $values
     * @param string                             $action Where the values go, like the action of a form or the formaction of its button
     * @param 'get'|'post'|'dialog'              $method How, like the method of a form or the formmethod of its button: dialog closes the dialog the form is in
     */
    public function submit(array $values, ?AbstractWidget $submitter = null, string $action = '', string $method = 'get'): void
    {
        $this->dispatch(new FormSubmitEvent($this, $values, $submitter, $action, $method));
    }
}
