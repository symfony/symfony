<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Input\Key;

/**
 * Loader that can be cancelled with Escape key.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CancellableLoaderWidget extends LoaderWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    private bool $cancelled = false;

    public function __construct(
        string $message = 'Loading...',
    ) {
        parent::__construct($message);
    }

    /**
     * Check if the loader was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * Reset the cancelled state.
     */
    public function reset(): void
    {
        $this->cancelled = false;
    }

    public function start(): void
    {
        parent::start();
        $this->cancelled = false;
    }

    /**
     * @param callable(CancelEvent): void $callback
     *
     * @return $this
     */
    public function onCancel(callable $callback): static
    {
        return $this->on(CancelEvent::class, $callback);
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }

        if ($this->getKeybindings()->matches($data, 'select_cancel')) {
            $this->cancelled = true;
            $this->dispatch(new CancelEvent($this));
        }
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return [
            'select_cancel' => [Key::ESCAPE, 'ctrl+c'],
        ];
    }
}
