<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Helper;

/**
 * SlotsHelper manages template slots.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SlotsHelper extends Helper
{
    protected $slots = [];
    protected $openSlots = [];

    /**
     * Starts a new slot.
     *
     * This method starts an output buffer that will be
     * closed when the stop() method is called.
     *
     * @return void
     *
     * @throws \InvalidArgumentException if a slot with the same name is already started
     */
    public function start(string $name)
    {
        if (\in_array($name, $this->openSlots)) {
            throw new \InvalidArgumentException(sprintf('A slot named "%s" is already started.', $name));
        }

        $this->openSlots[] = $name;
        $this->slots[$name] = '';

        ob_start();
        ob_implicit_flush(0);
    }

    /**
     * Stops a slot.
     *
     * @return void
     *
     * @throws \LogicException if no slot has been started
     */
    public function stop()
    {
        if (!$this->openSlots) {
            throw new \LogicException('No slot started.');
        }

        $name = array_pop($this->openSlots);

        $this->slots[$name] = ob_get_clean();
    }

    /**
     * Returns true if the slot exists.
     */
    public function has(string $name): bool
    {
        return isset($this->slots[$name]);
    }

    /**
     * Gets the slot value.
     */
    public function get(string $name, bool|string $default = false): string
    {
        return $this->slots[$name] ?? $default;
    }

    /**
     * Sets a slot value.
     *
     * @return void
     */
    public function set(string $name, string $content)
    {
        $this->slots[$name] = $content;
    }

    /**
     * Outputs a slot.
     *
     * @return bool true if the slot is defined or if a default content has been provided, false otherwise
     */
    public function output(string $name, bool|string $default = false): bool
    {
        if (!isset($this->slots[$name])) {
            if (false !== $default) {
                echo $default;

                return true;
            }

            return false;
        }

        echo $this->slots[$name];

        return true;
    }

    /**
     * Returns the canonical name of this helper.
     */
    public function getName(): string
    {
        return 'slots';
    }
}
