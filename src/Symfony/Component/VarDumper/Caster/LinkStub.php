<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

/**
 * Represents a file or a URL.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class LinkStub extends ConstStub
{
    public function __construct($file, $line = 0)
    {
        $this->value = $file;

        if (is_string($file)) {
            $this->type = self::TYPE_STRING;
            $this->class = preg_match('//u', $file) ? self::STRING_UTF8 : self::STRING_BINARY;

            if (0 === strpos($file, 'file://')) {
                $file = substr($file, 7);
            } elseif (false !== strpos($file, '://')) {
                $this->attr['href'] = $file;

                return;
            }
            if (file_exists($file)) {
                if ($line) {
                    $this->attr['line'] = $line;
                }
                $this->attr['file'] = realpath($file);

                if ($this->attr['file'] === $file) {
                    $ellipsis = explode(DIRECTORY_SEPARATOR, $file);
                    $this->attr['ellipsis'] = 3 < count($ellipsis) ? 2 + strlen(implode(array_slice($ellipsis, -2))) : 0;
                }
            }
        }
    }
}
