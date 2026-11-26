<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Exception;

use Symfony\Component\HtmlRenderer\Exception\ExceptionInterface;
use Symfony\Component\Tui\Exception\RenderException as TuiRenderException;

/**
 * @experimental
 */
class RenderException extends TuiRenderException implements ExceptionInterface
{
}
