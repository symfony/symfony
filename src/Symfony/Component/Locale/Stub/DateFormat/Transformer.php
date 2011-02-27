<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub\DateFormat;

/**
 * Parser and formatter for date formats
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
abstract class Transformer
{
    protected $namedCapture;

    public function __construct($namedCapture)
    {
        $this->namedCapture = $namedCapture;
    }

    public function getNamedCapture()
    {
        return $this->namedCapture;
    }

    public function addNamedCapture($regExp, $lenght = 1)
    {
        $namedCapture = $this->getNamedCapture();
        $namedCapture = str_repeat($namedCapture, $lenght);
        return '?P<'.$namedCapture.'>' . $regExp;
    }

    abstract public function format(\DateTime $dateTime, $length);
    abstract public function getReverseMatchingRegExp($length);
    abstract public function extractDateOptions($matched, $length);

    protected function padLeft($value, $length)
    {
        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }
}
