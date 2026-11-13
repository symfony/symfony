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

use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Style\Color;

/**
 * A color field, like an HTML <input type="color">: the color typed as #rrggbb, a swatch of it before, in the box of
 * the field, like browsers draw the color in theirs.
 *
 * @experimental
 */
class ColorInputWidget extends ContainerWidget implements CompositeControlInterface
{
    private ?Color $swatch = null;

    public function __construct(
        private readonly InputWidget $input,
    ) {
        $this->input->on(ChangeEvent::class, function (): void {
            $this->paint();
        });
        $this->paint();

        $this->add($this->input);
    }

    public function getControl(): AbstractWidget
    {
        return $this->input;
    }

    public function getInput(): InputWidget
    {
        return $this->input;
    }

    /**
     * The color of the swatch, null while the value is not a color.
     */
    public function getSwatchColor(): ?Color
    {
        return $this->swatch;
    }

    /**
     * The swatch in the color of the value, the prompt of the field; a dim pattern while it is not a color.
     */
    private function paint(): void
    {
        $value = $this->input->getValue();
        $this->swatch = 1 === preg_match('/^#[0-9a-f]{6}$/i', $value) ? Color::hex($value) : null;
        // Only the color of the text set and reset: the field keeps its own background around it
        $this->input->setPrompt(null !== $this->swatch ? $this->swatch->toForegroundCode()."██\e[39m " : "\e[2m░░\e[22m ");
    }
}
