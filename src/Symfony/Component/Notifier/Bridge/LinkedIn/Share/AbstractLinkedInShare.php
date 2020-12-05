<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LinkedIn\Share;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 *
 * @experimental in 5.3
 */
abstract class AbstractLinkedInShare
{
    protected $options = [];

    public function toArray(): array
    {
        return $this->options;
    }
}
