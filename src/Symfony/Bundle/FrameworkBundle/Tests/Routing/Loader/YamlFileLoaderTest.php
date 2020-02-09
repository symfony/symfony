<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing\Loader;

use Symfony\Bundle\FrameworkBundle\Routing\Loader\YamlFileLoader;
use Symfony\Component\Config\Loader\LoaderInterface;

class YamlFileLoaderTest extends AbstractLoaderTest
{
    protected function getLoader(): LoaderInterface
    {
        return new YamlFileLoader($this->getLocator());
    }

    protected function getType(): string
    {
        return 'yaml';
    }

    /**
     * @dataProvider getPathsToInvalidFiles
     */
    public function testLoadThrowsExceptionWithInvalidFile(string $filePath, string $exception)
    {
        $loader = $this->getLoader();

        $message = sprintf($exception, __DIR__.'/../../Fixtures/Resources/config/routing/'.$filePath);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(str_replace('/', \DIRECTORY_SEPARATOR, $message));

        $loader->load($filePath);
    }

    public function getPathsToInvalidFiles()
    {
        yield 'defining controller' => ['with_controller_attribute.yaml', 'The routing file "%s" must not specify both the "controller" and the "template" keys for "invalid_route".'];
        yield 'defining template and redirect' => ['template_and_redirect.yaml', 'The routing file "%s" must not specify only one route type among "template", "redirect_to_route" keys for "invalid_route".'];
    }
}
