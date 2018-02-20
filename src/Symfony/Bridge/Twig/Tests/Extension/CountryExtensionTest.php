<?php

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\CountryExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader as TwigArrayLoader;

class CountryExtensionTest extends TestCase
{
    protected function getTemplate($template)
    {
        if (is_array($template)) {
            $loader = new TwigArrayLoader($template);
        } else {
            $loader = new TwigArrayLoader(array('index' => $template));
        }

        $twig = new Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new CountryExtension());

        return $twig->loadTemplate('index');
    }

    public static function dataProvider()
    {
        return [
            ['US', 'United States'],
            ['ES', 'Spain'],
            ['PT', 'Portugal'],
            ['BR', 'Brazil'],
        ];
    }

    public function testEmptyCountryIso()
    {
        $output = $this->getTemplate("{{ ''|country }}")->render([]);
        $this->assertEmpty($output);
    }


    /**
     * @dataProvider dataProvider
     */
    public function testGetCountryName($countryIso, $expected)
    {
        $output = $this->getTemplate("{{ '$countryIso'|country }}")->render([]);
        $this->assertEquals($expected, $output);
    }
}
