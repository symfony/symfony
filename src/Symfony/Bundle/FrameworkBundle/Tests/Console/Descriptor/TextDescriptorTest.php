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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Descriptor\TextDescriptor;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class TextDescriptorTest extends AbstractDescriptorTestCase
{
    private static ?FileLinkFormatter $fileLinkFormatter = null;

    protected static function getDescriptor()
    {
        return new TextDescriptor(static::$fileLinkFormatter);
    }

    protected static function getFormat()
    {
        return 'txt';
    }

    public static function getDescribeRouteWithControllerLinkTestData()
    {
        return static::getDescribeRouteWithControllerTestData('_link');
    }

    #[DataProvider('getDescribeRouteWithControllerLinkTestData')]
    public function testDescribeRouteWithControllerLink(Route $route, $expectedDescription, $file)
    {
        static::$fileLinkFormatter = new FileLinkFormatter('myeditor://open?file=%f&line=%l');
        $this->assertDescription(static::expandFixturePlaceholders($expectedDescription), $route, ['raw_text' => false]);
    }

    public static function getDescribeRouteWithControllerLinkInRawModeTestData()
    {
        return static::getDescribeRouteWithControllerTestData('_link_raw');
    }

    #[DataProvider('getDescribeRouteWithControllerLinkInRawModeTestData')]
    public function testDescribeRouteWithControllerLinkInRawMode(Route $route, $expectedDescription, $file)
    {
        static::$fileLinkFormatter = new FileLinkFormatter('myeditor://open?file=%f&line=%l');
        $this->assertDescription($expectedDescription, $route);
    }

    public function testDescribeRouteCollectionWithControllerLink()
    {
        static::$fileLinkFormatter = new FileLinkFormatter('myeditor://open?file=%f&line=%l');
        $expectedDescription = file_get_contents(__DIR__.'/../../Fixtures/Descriptor/route_collection_1_link.txt');
        $this->assertDescription(static::expandFixturePlaceholders($expectedDescription), static::getRouteCollectionWithControllers(), ['show_controllers' => true, 'raw_text' => false]);
    }

    public function testDescribeRouteCollectionWithControllerLinkInRawMode()
    {
        static::$fileLinkFormatter = new FileLinkFormatter('myeditor://open?file=%f&line=%l');
        $expectedDescription = file_get_contents(__DIR__.'/../../Fixtures/Descriptor/route_collection_1_link_raw.txt');
        $this->assertDescription($expectedDescription, static::getRouteCollectionWithControllers(), ['show_controllers' => true]);
    }

    protected function normalizeOutput(string $output): string
    {
        $output = str_replace(\PHP_EOL, "\n", $output);

        return preg_replace_callback("/\e\[([0-9]+)X\e\[([0-9]+)C/", static fn ($m) => str_repeat(' ', $m[1]), $output);
    }

    private static function getDescribeRouteWithControllerTestData(string $suffix): array
    {
        $getDescribeData = static::getDescribeRouteTestData();

        foreach ($getDescribeData as &$data) {
            $routeStub = $data[0];
            $routeStub->setDefault('_controller', \sprintf('%s::%s', MyController::class, '__invoke'));
            $file = preg_replace('#(\..*?)$#', $suffix.'$1', $data[2]);
            $data = [$routeStub, file_get_contents(__DIR__.'/../../Fixtures/Descriptor/'.$file), $file];
        }

        return $getDescribeData;
    }

    private static function getRouteCollectionWithControllers(): RouteCollection
    {
        $collection = new RouteCollection();
        foreach (ObjectsProvider::getRoutes() as $name => $route) {
            $route->setDefault('_controller', \sprintf('%s::%s', MyController::class, '__invoke'));
            $collection->add($name, $route);
        }

        return $collection;
    }

    private static function expandFixturePlaceholders(string $expectedDescription): string
    {
        return str_replace(['[:file:]', '[:line:]'], [__FILE__, (new \ReflectionMethod(MyController::class, '__invoke'))->getStartLine()], $expectedDescription);
    }
}

class MyController
{
    public function __invoke()
    {
    }
}
