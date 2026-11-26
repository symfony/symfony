<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer;

final class FormSubmission
{
    public function __construct(public readonly \Dom\Element $form, public readonly string $action, public readonly string $method, public readonly array $values)
    {
    }
}
