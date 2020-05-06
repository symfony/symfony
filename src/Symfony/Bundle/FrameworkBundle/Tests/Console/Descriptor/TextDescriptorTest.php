<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor;

use Symfony\Bundle\FrameworkBundle\Console\Descriptor\TextDescriptor;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
use Symfony\Component\Routing\Route;

class TextDescriptorTest extends AbstractDescriptorTest
{
    private $fileLinkFormatter = null;

    protected function setUp(): void
    {
        putenv('COLUMNS=121');
    }

    protected function tearDown(): void
    {
        putenv('COLUMNS');
    }

    protected function getDescriptor()
    {
        return new TextDescriptor($this->fileLinkFormatter);
    }

    protected function getFormat()
    {
        return 'txt';
    }

    public function getDescribeRouteWithControllerLinkTestData()
    {
        $getDescribeData = $this->getDescribeRouteTestData();

        foreach ($getDescribeData as $key => &$data) {
            $routeStub = $data[0];
            $routeStub->setDefault('_controller', sprintf('%s::%s', MyController::class, '__invoke'));
            $file = $data[2];
            $file = preg_replace('#(\..*?)$#', '_link$1', $file);
            $data = file_get_contents(__DIR__.'/../../Fixtures/Descriptor/'.$file);
            $data = [$routeStub, $data, $file];
        }

        return $getDescribeData;
    }

    /** @dataProvider getDescribeRouteWithControllerLinkTestData */
    public function testDescribeRouteWithControllerLink(Route $route, $expectedDescription)
    {
        $this->fileLinkFormatter = new FileLinkFormatter('myeditor://open?file=%f&line=%l');
        parent::testDescribeRoute($route, str_replace('[:file:]', __FILE__, $expectedDescription));
    }
}

class MyController
{
    public function __invoke()
    {
    }
}
