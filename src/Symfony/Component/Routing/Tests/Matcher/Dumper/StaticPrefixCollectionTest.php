<?php

namespace Symfony\Component\Routing\Tests\Matcher\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Matcher\Dumper\StaticPrefixCollection;
use Symfony\Component\Routing\Route;

class StaticPrefixCollectionTest extends TestCase
{
    /**
     * @dataProvider routeProvider
     */
    public function testGrouping(array $routes, $expected)
    {
        $collection = new StaticPrefixCollection('/');

        foreach ($routes as $route) {
            list($path, $name) = $route;
            $staticPrefix = (new Route($path))->compile()->getStaticPrefix();
            $collection->addRoute($staticPrefix, [$name]);
        }

        $dumped = $this->dumpCollection($collection);
        $this->assertEquals($expected, $dumped);
    }

    public function routeProvider()
    {
        return [
            'Simple - not nested' => [
                [
                    ['/', 'root'],
                    ['/prefix/segment/', 'prefix_segment'],
                    ['/leading/segment/', 'leading_segment'],
                ],
                <<<EOF
root
prefix_segment
leading_segment
EOF
            ],
            'Nested - small group' => [
                [
                    ['/', 'root'],
                    ['/prefix/segment/aa', 'prefix_segment'],
                    ['/prefix/segment/bb', 'leading_segment'],
                ],
                <<<EOF
root
/prefix/segment/
-> prefix_segment
-> leading_segment
EOF
            ],
            'Nested - contains item at intersection' => [
                [
                    ['/', 'root'],
                    ['/prefix/segment/', 'prefix_segment'],
                    ['/prefix/segment/bb', 'leading_segment'],
                ],
                <<<EOF
root
/prefix/segment/
-> prefix_segment
-> leading_segment
EOF
            ],
            'Simple one level nesting' => [
                [
                    ['/', 'root'],
                    ['/group/segment/', 'nested_segment'],
                    ['/group/thing/', 'some_segment'],
                    ['/group/other/', 'other_segment'],
                ],
                <<<EOF
root
/group/
-> nested_segment
-> some_segment
-> other_segment
EOF
            ],
            'Retain matching order with groups' => [
                [
                    ['/group/aa/', 'aa'],
                    ['/group/bb/', 'bb'],
                    ['/group/cc/', 'cc'],
                    ['/(.*)', 'root'],
                    ['/group/dd/', 'dd'],
                    ['/group/ee/', 'ee'],
                    ['/group/ff/', 'ff'],
                ],
                <<<EOF
/group/
-> aa
-> bb
-> cc
root
/group/
-> dd
-> ee
-> ff
EOF
            ],
            'Retain complex matching order with groups at base' => [
                [
                    ['/aaa/111/', 'first_aaa'],
                    ['/prefixed/group/aa/', 'aa'],
                    ['/prefixed/group/bb/', 'bb'],
                    ['/prefixed/group/cc/', 'cc'],
                    ['/prefixed/(.*)', 'root'],
                    ['/prefixed/group/dd/', 'dd'],
                    ['/prefixed/group/ee/', 'ee'],
                    ['/prefixed/', 'parent'],
                    ['/prefixed/group/ff/', 'ff'],
                    ['/aaa/222/', 'second_aaa'],
                    ['/aaa/333/', 'third_aaa'],
                ],
                <<<EOF
/aaa/
-> first_aaa
-> second_aaa
-> third_aaa
/prefixed/
-> /prefixed/group/
-> -> aa
-> -> bb
-> -> cc
-> root
-> /prefixed/group/
-> -> dd
-> -> ee
-> -> ff
-> parent
EOF
            ],

            'Group regardless of segments' => [
                [
                    ['/aaa-111/', 'a1'],
                    ['/aaa-222/', 'a2'],
                    ['/aaa-333/', 'a3'],
                    ['/group-aa/', 'g1'],
                    ['/group-bb/', 'g2'],
                    ['/group-cc/', 'g3'],
                ],
                <<<EOF
/aaa-
-> a1
-> a2
-> a3
/group-
-> g1
-> g2
-> g3
EOF
            ],
        ];
    }

    private function dumpCollection(StaticPrefixCollection $collection, $prefix = '')
    {
        $lines = [];

        foreach ($collection->getRoutes() as $item) {
            if ($item instanceof StaticPrefixCollection) {
                $lines[] = $prefix.$item->getPrefix();
                $lines[] = $this->dumpCollection($item, $prefix.'-> ');
            } else {
                $lines[] = $prefix.implode(' ', $item);
            }
        }

        return implode("\n", $lines);
    }
}
