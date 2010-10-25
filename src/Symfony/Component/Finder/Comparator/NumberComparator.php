<?php

namespace Symfony\Component\Finder\Comparator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * NumberComparator compiles a simple comparison to an anonymous
 * subroutine, which you can call with a value to be tested again.
 *
 * Now this would be very pointless, if NumberCompare didn't understand
 * magnitudes.
 *
 * The target value may use magnitudes of kilobytes (k, ki),
 * megabytes (m, mi), or gigabytes (g, gi).  Those suffixed
 * with an i use the appropriate 2**n version in accordance with the
 * IEC standard: http://physics.nist.gov/cuu/Units/binary.html
 *
 * Based on the Perl Number::Compare module.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com> PHP port
 * @author     Richard Clamp <richardc@unixbeard.net> Perl version
 * @copyright  2004-2005 Fabien Potencier <fabien.potencier@symfony-project.com>
 * @copyright  2002 Richard Clamp <richardc@unixbeard.net>
 * @see        http://physics.nist.gov/cuu/Units/binary.html
 */
class NumberComparator extends Comparator
{
    /**
     * Constructor.
     *
     * @param string $test A comparison string
     *
     * @throws \InvalidArgumentException If the test is not understood
     */
    public function __construct($test)
    {
        if (!preg_match('#^\s*([<>=]=?)?\s*([0-9\.]+)\s*([kmg]i?)?\s*$#i', $test, $matches)) {
            throw new \InvalidArgumentException(sprintf('Don\'t understand "%s" as a number test.', $test));
        }

        $target = $matches[2];
        $magnitude = strtolower(isset($matches[3]) ? $matches[3] : '');
        switch ($magnitude) {
            case 'k':
                $target *= 1000;
                break;
            case 'ki':
                $target *= 1024;
                break;
            case 'm':
                $target *= 1000000;
                break;
            case 'mi':
                $target *= 1024*1024;
                break;
            case 'g':
                $target *= 1000000000;
                break;
            case 'gi':
                $target *= 1024*1024*1024;
                break;
        }

        $this->setTarget($target);
        $this->setOperator(isset($matches[1]) ? $matches[1] : '==');
    }
}
