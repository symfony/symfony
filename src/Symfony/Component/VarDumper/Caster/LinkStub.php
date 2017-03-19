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
    public function __construct($label, $line = 0, $href = null)
    {
        $this->value = $label;

        if (null === $href) {
            $href = $label;
        }
        if (is_string($href)) {
            if (0 === strpos($href, 'file://')) {
                if ($href === $label) {
                    $label = substr($label, 7);
                }
                $href = substr($href, 7);
            } elseif (false !== strpos($href, '://')) {
                $this->attr['href'] = $href;

                return;
            }
            if (file_exists($href)) {
                if ($line) {
                    $this->attr['line'] = $line;
                }
                $this->attr['file'] = realpath($href) ?: $href;

                if ($this->attr['file'] === $label && 3 < count($ellipsis = explode(DIRECTORY_SEPARATOR, $href))) {
                    $this->attr['ellipsis'] = 2 + strlen(implode(array_slice($ellipsis, -2)));
                }
            }
        }
    }
}
