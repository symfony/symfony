<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Widget;

use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Internal\ContextBridge;
use Symfony\Bridge\TuiHtml\Render\FacadePresentation;
use Symfony\Bridge\TuiHtml\Render\RenderContext;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\Tui\Render\RenderContext as NativeContext;
use Symfony\Component\Tui\Widget\SelfRenderingInterface;

/** @internal Opt-in migration root; the native body and descendants remain unchanged. */
final class HtmlRootWidget extends ContainerWidget implements SelfRenderingInterface
{
    private FacadePresentation $presentation;
    private ?MediaContext $media = null;
    private string $defaultForeground = 'default';
    private ?\Closure $nativeStyleResolver = null;

    public function __construct(private readonly CssStyleSheet $htmlStyleSheet)
    {
        $this->presentation = new FacadePresentation($htmlStyleSheet);
    }

    public function setMediaContext(MediaContext $media): void
    {
        if ($media != $this->media) {
            $this->media = $media;
            $this->invalidate();
        }
    }

    public function setDefaultForeground(string $color): void
    {
        if ($color !== $this->defaultForeground) {
            $this->defaultForeground = $color;
            $this->presentation->setDefaultForeground($color);
            $this->invalidate();
        }
    }

    public function setNativeStyleResolver(\Closure $resolver): void
    {
        if ($resolver !== $this->nativeStyleResolver) {
            $this->nativeStyleResolver = $resolver;
            $this->presentation->setNativeStyleResolver($resolver);
            $this->invalidate();
        }
    }

    public function getPresentation(): FacadePresentation
    {
        return $this->presentation;
    }

    public function getHtmlStyleSheet(): CssStyleSheet
    {
        return $this->htmlStyleSheet;
    }

    protected function onDetach(): void
    {
        $this->presentation->release($this);
    }

    public function render(NativeContext|RenderContext $context): array
    {
        $context = ContextBridge::computed($context);

        return $this->presentation->render($this, $context, ($this->media ?? MediaContext::fromEnvironment())->withSize($context->getColumns(), $context->getRows()));
    }
}
