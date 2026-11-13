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
 * Holds a nested document, like an <iframe srcdoc>: the stylesheets of the page around it stop at it, and the
 * stylesheet of the document inside styles its content only.
 *
 * @experimental
 */
class FrameWidget extends ContainerWidget
{
}
