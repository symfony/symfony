<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ValueExporter\Formatter;

/**
 * Returns a string representation of a __PHP_Incomplete_Class instance.
 *
 * @author Yonel Ceruto González <yonelceruto@gmail.com>
 * @author Jules Pietri <jules@heahprod.com>
 */
class PhpIncompleteClassToStringFormatter implements StringFormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports($value)
    {
        return $value instanceof \__PHP_Incomplete_Class;
    }

    /**
     * {@inheritdoc}
     */
    public function formatToString($value)
    {
        return sprintf('__PHP_Incomplete_Class(%s)', $this->getClassNameFromIncomplete($value));
    }

    private function getClassNameFromIncomplete(\__PHP_Incomplete_Class $value)
    {
        $array = new \ArrayObject($value);

        return $array['__PHP_Incomplete_Class_Name'];
    }
}
