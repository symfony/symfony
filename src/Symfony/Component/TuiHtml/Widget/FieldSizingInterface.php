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

/**
 * A form control of a fixed size that a style provider can size to its content instead, like CSS field-sizing: content.
 *
 * @experimental
 */
interface FieldSizingInterface
{
    public function setContentSized(bool $contentSized): void;
}
