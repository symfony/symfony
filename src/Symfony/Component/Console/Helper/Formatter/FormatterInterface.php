<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper\Formatter;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
interface FormatterInterface
{
    /**
     * @return array|string
     */
    public function format();
}
