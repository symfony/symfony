<?php

declare(strict_types=1);

namespace Symfony\Component\Translation\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Util\IntlMessageConverter;

class IntlMessageConverterTest extends TestCase
{
    /**
     * @dataProvider getTestData
     */
    public function testConvert($input, $output)
    {
        $result = IntlMessageConverter::convert($input);
        $this->assertEquals($output, $result);
    }

    /**
     * We cannot use negative Inf together with positive Inf.
     */
    public function testImpossibleConvert()
    {
        $this->expectException(\LogicException::class);
        IntlMessageConverter::convert(']-Inf, -2[ Negative|]1,Inf[ Positive');
    }

    public function getTestData()
    {
        yield array('|', '|');
        yield array(
            '{0} There are no apples|{1} There is one apple|]1,Inf[ There %name% are %count% apples',
            <<<ICU
{ COUNT, plural,
  =0 {There are no apples}
  =1 {There is one apple}
  other {There {name} are # apples}
}
ICU
            );
        yield array('foo', 'foo');
        yield array('Hello %username%', 'Hello {username}');

        yield array(
            ']-7, -2] Negative|[2, 7] Small|]10,Inf[ Many',
            <<<ICU
{ COUNT, plural,
  =-6 {Negative}
  =-5 {Negative}
  =-4 {Negative}
  =-3 {Negative}
  =-2 {Negative}
  =2 {Small}
  =3 {Small}
  =4 {Small}
  =5 {Small}
  =6 {Small}
  =7 {Small}
  other {Many}
}
ICU
        );

        // Test overlapping, make sure we have the same behaviour as Symfony
        yield array(
            '[2,5] Small|]3,Inf[ Many',
            <<<ICU
{ COUNT, plural,
  =2 {Small}
  =3 {Small}
  =4 {Small}
  =5 {Small}
  other {Many}
}
ICU
        );
    }
}
