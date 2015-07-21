<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/20/15
 * Time: 10:31 PM
 */

namespace Symfony\Component\HttpFoundation\Tests\Header;


use Symfony\Component\HttpFoundation\Header\IfNoneMatch;

class IfNoneMatchTest extends \PHPUnit_Framework_TestCase
{
    public function testStringification()
    {
        $etagOne = 'randomly_generated_etag';
        $etagTwo = 'randomly_generated_etag_2';
        $header = sprintf('%s, %s, %s', $etagOne, $etagTwo, 'etagThree');

        $this->assertEquals(
            new IfNoneMatch(array($etagOne, $etagTwo, 'etagThree')),
            IfNoneMatch::fromString($header)
        );
        $this->assertEquals(
            $header,
            IfNoneMatch::fromString($header)
        );
    }

    public function testGetETags()
    {
        $etagOne = 'randomly_generated_etag';
        $etagTwo = 'randomly_generated_etag_2';
        $header = sprintf('%s, %s, %s', $etagOne, $etagTwo, 'etagThree');
        $ifNoneMatch = new IfNoneMatch(array($etagOne, $etagTwo, 'etagThree'));
        $this->assertEquals(
            array($etagOne, $etagTwo, 'etagThree'),
            $ifNoneMatch->getETags()
        );
        $this->assertEquals(
            array($etagOne, $etagTwo, 'etagThree'),
            IfNoneMatch::fromString($header)->getETags()
        );
    }
}
