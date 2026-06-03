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
        $getDescribeData = static::getDescribeRouteTestData();

        foreach ($getDescribeData as $key => &$data) {
            $routeStub = $data[0];
            $routeStub->setDefault('_controller', \sprintf('%s::%s', MyController::class, '__invoke'));
            $file = $data[2];
            $file = preg_replace('#(\..*?)$#', '_link$1', $file);
            $data = file_get_contents(__DIR__.'/../../Fixtures/Descriptor/'.$file);
            $data = [$routeStub, $data, $file];
        }

        return $getDescribeData;
    }

    #[DataProvider('getDescribeRouteWithControllerLinkTestData')]
    public function testDescribeRouteWithControllerLink(Route $route, $expectedDescription, $file)
    {
        static::$fileLinkFormatter = new FileLinkFormatter('myeditor://open?file=%f&line=%l');
        $expectedDescription = str_replace('[:file:]', __FILE__, $expectedDescription);
        $expectedDescription = str_replace('[:line:]', (new \ReflectionMethod(MyController::class, '__invoke'))->getStartLine(), $expectedDescription);
        parent::testDescribeRoute($route, $expectedDescription, $file);
    }

    protected function normalizeOutput(string $output): string
    {
        $output = str_replace(\PHP_EOL, "\n", $output);

        return preg_replace_callback("/\e\[([0-9]+)X\e\[([0-9]+)C/", static fn ($m) => str_repeat(' ', $m[1]), $output);
    }
}

class MyController
{
    public function __invoke()
    {
    }
}
