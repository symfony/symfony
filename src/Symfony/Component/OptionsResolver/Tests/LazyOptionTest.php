<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver\Tests;

use Symfony\Component\OptionsResolver\LazyOption;
use Symfony\Component\OptionsResolver\Options;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LazyOptionTest extends \PHPUnit_Framework_TestCase
{
    public function testDontCacheEvaluatedPreviousValue()
    {
        $previousValue = new LazyOption(function (Options $options) {
            return $options['foo'];
        });

        $lazyOption = new LazyOption(function (Options $options, $previousValue) {
            return $previousValue;
        }, $previousValue);

        // If provided with two different option sets, two different results
        // should be returned
        $options1 = new Options();
        $options1['foo'] = 'bar';

        $this->assertSame('bar', $lazyOption->evaluate($options1));

        $options2 = new Options();
        $options2['foo'] = 'boo';

        $this->assertSame('boo', $lazyOption->evaluate($options2));
    }
}
