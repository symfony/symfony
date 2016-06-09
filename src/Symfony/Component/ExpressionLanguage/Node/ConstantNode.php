<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Node;

use Symfony\Component\ExpressionLanguage\Compiler;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class ConstantNode extends Node
{
    public function __construct($value)
    {
        parent::__construct(
            array(),
            array('value' => $value)
        );
    }

    public function compile(Compiler $compiler)
    {
        $compiler->repr($this->attributes['value']);
    }

    public function evaluate($functions, $values)
    {
        return $this->attributes['value'];
    }

    public function dump()
    {
        return $this->dumpValue($this->attributes['value']);
    }

    private function dumpValue($value)
    {
        switch (true) {
            case true === $value:
                return 'true';

            case false === $value:
                return 'false';

            case null === $value:
                return 'null';

            case is_numeric($value):
                return $value;

            case is_array($value):
                if ($this->isHash($value)) {
                    $str = '{';

                    foreach ($value as $key => $v) {
                        if (is_int($key)) {
                            $str .= sprintf('%s: %s, ', $key, $this->dumpValue($v));
                        } else {
                            $str .= sprintf('"%s": %s, ', $this->dumpEscaped($key), $this->dumpValue($v));
                        }
                    }

                    return rtrim($str, ', ').'}';
                }

                $str = '[';

                foreach ($value as $key => $v) {
                    $str .= sprintf('%s, ', $this->dumpValue($v));
                }

                return rtrim($str, ', ').']';

            default:
                return sprintf('"%s"', $this->dumpEscaped($value));
        }
    }
}
