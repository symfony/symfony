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

/**
 * A control whose lines a style provider can keep whole, scrolled sideways, like white-space: pre on a textarea.
 *
 * @experimental
 */
interface LineWrappingInterface
{
    public function setLineWrapping(bool $wrap): void;
}
