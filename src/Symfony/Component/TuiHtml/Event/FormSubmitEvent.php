<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Event;

use Symfony\Component\Tui\Event\AbstractEvent;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Dispatched by a form when it is submitted, with the values of its controls.
 *
 * @experimental
 */
class FormSubmitEvent extends AbstractEvent
{
    /**
     * @param array<string, string|list<string>> $values    By control name, like $_POST: a name ending with [] collects a list
     * @param AbstractWidget|null                $submitter The button that submitted the form, null for an implicit submission
     * @param string                             $action    Where the values go, like the action of a form or the formaction of its button
     * @param 'get'|'post'|'dialog'              $method    How, like the method of a form or the formmethod of its button
     */
    public function __construct(AbstractWidget $target, private readonly array $values, private readonly ?AbstractWidget $submitter = null, private readonly string $action = '', private readonly string $method = 'get')
    {
        parent::__construct($target);
    }

    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return 'get'|'post'|'dialog'
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function getSubmitter(): ?AbstractWidget
    {
        return $this->submitter;
    }
}
