<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Style\StyleProviderInterface;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;

class FacadeProviderLifecycleTest extends TestCase
{
    public function testCustomProvidersPrepareOnceBeforeAnyResolutionWithTheApplicationViewport()
    {
        $provider = new class implements StyleProviderInterface {
            public array $preparations = [];
            public string $color = 'blue';
            private ?string $previous = null;

            public function prepare(AbstractWidget $root, MediaContext $media): void
            {
                $this->preparations[] = [$root, $media->columns, $media->rows];
                if ($this->previous !== $this->color) {
                    $this->previous = $this->color;
                    $invalidate = static function (AbstractWidget $widget) use (&$invalidate): void {
                        $widget->invalidate();
                        if ($widget instanceof \Symfony\Component\Tui\Widget\ParentInterface) {
                            foreach ($widget->all() as $child) {
                                $invalidate($child);
                            }
                        }
                    };
                    $invalidate($root);
                }
            }

            public function getStyles(AbstractWidget $widget): array
            {
                if (!$this->preparations) {
                    throw new \LogicException('A provider was resolved before preparation.');
                }

                return [new Style()->withColor($this->color)];
            }

            public function getElementStyles(AbstractWidget $widget, string $element): array
            {
                return [];
            }
        };
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p>A</p><p>B</p>');
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->getStyleSheet()->addProvider($provider);
        $renderer->renderFrame($outer, 30, 8);
        self::assertSame([[$outer, 30, 8]], $provider->preparations);
        self::assertSame('#0000ee', $view->getRoot()->getPresentation()->getResult()->getDisplayList()->getOperations()[0]['color']);
        $provider->color = 'red';
        $renderer->renderFrame($outer, 25, 6);
        self::assertSame([[$outer, 30, 8], [$outer, 25, 6]], $provider->preparations);
        self::assertSame('#cd0000', $view->getRoot()->getPresentation()->getResult()->getDisplayList()->getOperations()[0]['color']);
    }
}
