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
            $collection->addRoute($staticPrefix, array($name));
        }

        $dumped = $this->dumpCollection($collection);
        $this->assertEquals($expected, $dumped);
    }

    public function routeProvider()
    {
        return array(
            'Simple - not nested' => array(
                array(
                    array('/', 'root'),
                    array('/prefix/segment/', 'prefix_segment'),
                    array('/leading/segment/', 'leading_segment'),
                ),
                <<<EOF
root
prefix_segment
leading_segment
EOF
            ),
            'Nested - small group' => array(
                array(
                    array('/', 'root'),
                    array('/prefix/segment/aa', 'prefix_segment'),
                    array('/prefix/segment/bb', 'leading_segment'),
                ),
                <<<EOF
root
/prefix/segment/
-> prefix_segment
-> leading_segment
EOF
            ),
            'Nested - contains item at intersection' => array(
                array(
                    array('/', 'root'),
                    array('/prefix/segment/', 'prefix_segment'),
                    array('/prefix/segment/bb', 'leading_segment'),
                ),
                <<<EOF
root
/prefix/segment/
-> prefix_segment
-> leading_segment
EOF
            ),
            'Simple one level nesting' => array(
                array(
                    array('/', 'root'),
                    array('/group/segment/', 'nested_segment'),
                    array('/group/thing/', 'some_segment'),
                    array('/group/other/', 'other_segment'),
                ),
                <<<EOF
root
/group/
-> nested_segment
-> some_segment
-> other_segment
EOF
            ),
            'Retain matching order with groups' => array(
                array(
                    array('/group/aa/', 'aa'),
                    array('/group/bb/', 'bb'),
                    array('/group/cc/', 'cc'),
                    array('/(.*)', 'root'),
                    array('/group/dd/', 'dd'),
                    array('/group/ee/', 'ee'),
                    array('/group/ff/', 'ff'),
                ),
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
            ),
            'Retain complex matching order with groups at base' => array(
                array(
                    array('/aaa/111/', 'first_aaa'),
                    array('/prefixed/group/aa/', 'aa'),
                    array('/prefixed/group/bb/', 'bb'),
                    array('/prefixed/group/cc/', 'cc'),
                    array('/prefixed/(.*)', 'root'),
                    array('/prefixed/group/dd/', 'dd'),
                    array('/prefixed/group/ee/', 'ee'),
                    array('/prefixed/', 'parent'),
                    array('/prefixed/group/ff/', 'ff'),
                    array('/aaa/222/', 'second_aaa'),
                    array('/aaa/333/', 'third_aaa'),
                ),
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
            ),

            'Group regardless of segments' => array(
                array(
                    array('/aaa-111/', 'a1'),
                    array('/aaa-222/', 'a2'),
                    array('/aaa-333/', 'a3'),
                    array('/group-aa/', 'g1'),
                    array('/group-bb/', 'g2'),
                    array('/group-cc/', 'g3'),
                ),
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
            ),
        );
    }

    private function dumpCollection(StaticPrefixCollection $collection, $prefix = '')
    {
        $lines = array();

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
