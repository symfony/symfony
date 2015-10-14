<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Polyfill\Tests;

use Symfony\Component\Polyfill\Normalizer as pn;
use Normalizer as in;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @covers Symfony\Component\Polyfill\Normalizer::<!public>
 * @requires extension intl
 */
class NormalizerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstants()
    {
        $rpn = new \ReflectionClass('Symfony\Component\Polyfill\Normalizer');
        $rin = new \ReflectionClass('Normalizer');

        $rpn = $rpn->getConstants();
        $rin = $rin->getConstants();

        ksort($rpn);
        ksort($rin);

        $this->assertSame($rin, $rpn);
    }

    /**
     * @covers Symfony\Component\Polyfill\Normalizer::isNormalized
     */
    public function testIsNormalized()
    {
        $c = 'déjà';
        $d = in::normalize($c, pn::NFD);

        $this->assertTrue(pn::isNormalized(''));
        $this->assertTrue(pn::isNormalized('abc'));
        $this->assertTrue(pn::isNormalized($c));
        $this->assertTrue(pn::isNormalized($c, pn::NFC));
        $this->assertFalse(pn::isNormalized($d, pn::NFD)); // The current implementation defensively says false
        $this->assertFalse(pn::isNormalized($c, pn::NFD));
        $this->assertFalse(pn::isNormalized($d, pn::NFC));
        $this->assertFalse(pn::isNormalized("\xFF"));
    }

    /**
     * @covers Symfony\Component\Polyfill\Normalizer::normalize
     */
    public function testNormalize()
    {
        $c = in::normalize('déjà', pn::NFC).in::normalize('훈쇼™', pn::NFD);
        $this->assertSame($c, pn::normalize($c, pn::NONE));
        $this->assertSame($c, in::normalize($c, pn::NONE));

        $c = 'déjà 훈쇼™';
        $d = in::normalize($c, pn::NFD);
        $kc = in::normalize($c, pn::NFKC);
        $kd = in::normalize($c, pn::NFKD);

        $this->assertSame('', pn::normalize(''));
        $this->assertSame($c, pn::normalize($d));
        $this->assertSame($c, pn::normalize($d, pn::NFC));
        $this->assertSame($d, pn::normalize($c, pn::NFD));
        $this->assertSame($kc, pn::normalize($d, pn::NFKC));
        $this->assertSame($kd, pn::normalize($c, pn::NFKD));

        $this->assertFalse(pn::normalize($c, -1));
        $this->assertFalse(pn::normalize("\xFF"));
    }
}
