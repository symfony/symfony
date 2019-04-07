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
 * Loads an {@link ArrayChoiceList} instance from a callable returning an array of choices.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class CallbackChoiceLoader extends AbstractChoiceLoader
{
    private $callback;

    /**
     * @param callable $callback The callable returning an array of choices
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    protected function loadChoices(): array
    {
        return ($this->callback)();
    }
}
