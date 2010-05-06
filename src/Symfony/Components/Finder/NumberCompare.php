<?php

namespace Symfony\Components\Finder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * NumberCompare compiles a simple comparison to an anonymous
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
 * @package    Symfony
 * @subpackage Components_Finder
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com> PHP port
 * @author     Richard Clamp <richardc@unixbeard.net> Perl version
 * @copyright  2004-2005 Fabien Potencier <fabien.potencier@symfony-project.com>
 * @copyright  2002 Richard Clamp <richardc@unixbeard.net>
 * @see        http://physics.nist.gov/cuu/Units/binary.html
 */
class NumberCompare
{
    protected $target;
    protected $comparison;

    /**
     * Constructor.
     *
     * @param string $test A comparison string
     */
    public function __construct($test)
    {
        if (!preg_match('#^\s*([<>=]=?)?\s*([0-9\.]+)\s*([kmg]i?)?\s*$#i', $test, $matches))
        {
            throw new \InvalidArgumentException(sprintf('Don\'t understand "%s" as a test.', $test));
        }

        $this->target = $matches[2];
        $this->comparison = isset($matches[1]) ? $matches[1] : '==';

        $magnitude = strtolower(isset($matches[3]) ? $matches[3] : '');
        switch ($magnitude)
        {
            case 'k':
                $this->target *= 1000;
                break;
            case 'ki':
                $this->target *= 1024;
                break;
            case 'm':
                $this->target *= 1000000;
                break;
            case 'mi':
                $this->target *= 1024*1024;
                break;
            case 'g':
                $this->target *= 1000000000;
                break;
            case 'gi':
                $this->target *= 1024*1024*1024;
                break;
        }
    }

    /**
     * Tests a number against the test.
     *
     * @throws \InvalidArgumentException If the test is not understood
     */
    public function test($number)
    {
        if ($this->comparison === '>')
        {
            return ($number > $this->target);
        }

        if ($this->comparison === '>=')
        {
            return ($number >= $this->target);
        }

        if ($this->comparison === '<')
        {
            return ($number < $this->target);
        }

        if ($this->comparison === '<=')
        {
            return ($number <= $this->target);
        }

        return ($number == $this->target);
    }
}
