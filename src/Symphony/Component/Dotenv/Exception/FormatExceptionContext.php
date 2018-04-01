<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Dotenv\Exception;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 */
final class FormatExceptionContext
{
    private $data;
    private $path;
    private $lineno;
    private $cursor;

    public function __construct(string $data, string $path, int $lineno, int $cursor)
    {
        $this->data = $data;
        $this->path = $path;
        $this->lineno = $lineno;
        $this->cursor = $cursor;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getLineno()
    {
        return $this->lineno;
    }

    public function getDetails()
    {
        $before = str_replace("\n", '\n', substr($this->data, max(0, $this->cursor - 20), min(20, $this->cursor)));
        $after = str_replace("\n", '\n', substr($this->data, $this->cursor, 20));

        return '...'.$before.$after."...\n".str_repeat(' ', strlen($before) + 2).'^ line '.$this->lineno.' offset '.$this->cursor;
    }
}
