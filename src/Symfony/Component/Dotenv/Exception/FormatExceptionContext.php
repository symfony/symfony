<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dotenv\Exception;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class FormatExceptionContext
{
    public function __construct(
        private string $data,
        private string $path,
        private int $lineno,
        private int $cursor,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getLineno(): int
    {
        return $this->lineno;
    }

    public function getDetails(): string
    {
        $before = str_replace("\n", '\n', substr($this->data, max(0, $this->cursor - 20), min(20, $this->cursor)));
        $after = str_replace("\n", '\n', substr($this->data, $this->cursor, 20));

        return '...'.$before.$after."...\n".str_repeat(' ', \strlen($before) + 2).'^ line '.$this->lineno.' offset '.$this->cursor;
    }
}
