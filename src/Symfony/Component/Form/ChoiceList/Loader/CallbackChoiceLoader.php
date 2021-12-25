<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Loader;

/**
 * Loads an {@link ArrayChoiceList} instance from a callable returning iterable choices.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class CallbackChoiceLoader extends AbstractChoiceLoader
{
    private \Closure $callback;

    /**
     * @param callable $callback The callable returning iterable choices
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback instanceof \Closure ? $callback : \Closure::fromCallable($callback);
    }

    protected function loadChoices(): iterable
    {
        return ($this->callback)();
    }
}
