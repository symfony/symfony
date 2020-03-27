<?php


namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\DataTransformer\UidToStringTransformer;

class UidToStringTransformerTest extends TestCase
{
    public function dataProvider()
    {
        $data = [
            '',
            '',
            '',
            '',
            '',
            '',
        ];
    }

    public function testTransform()
    {

    }
}
