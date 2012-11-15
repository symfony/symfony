<?php

namespace Symfony\Component\Routing\Test\Matcher\Dumper;

use Symfony\Component\Routing\Matcher\Dumper\DumperCollection;

class DumperCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testGetRoot()
    {
        $a = new DumperCollection();

        $b = new DumperCollection();
        $a->add($b);

        $c = new DumperCollection();
        $b->add($c);

        $d = new DumperCollection();
        $c->add($d);

        $this->assertSame($a, $c->getRoot());
    }
}
