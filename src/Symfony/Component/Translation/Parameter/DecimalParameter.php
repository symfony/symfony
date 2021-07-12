<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Parameter;

use Symfony\Contracts\Translation\ParameterInterface;

/**
 * Wrapper around PHP NumberFormatter for decimal values.
 *
 * @author Sylvain Fabre <syl.fabre@gmail.com>
 */
class DecimalParameter implements ParameterInterface
{
    private $style;
    private $value;

    private $formatters = [];

    public function __construct($value, int $style = \NumberFormatter::DECIMAL)
    {
        $this->value = $value;
        $this->style = $style;
    }

    public function format(string $locale = null): string
    {
        if (!isset($this->formatters[$locale])) {
            $this->formatters[$locale] = new \NumberFormatter($locale, $this->style);
        }

        return $this->formatters[$locale]->format($this->value);
    }
}
