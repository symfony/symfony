<?php

namespace Symfony\Component\TuiHtml\Tests\Fixtures;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RendererInterface;
use Symfony\Component\Tui\Style\StyleSheet as NativeStyleSheet;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\TuiHtml\Render\Renderer;
use Symfony\Component\TuiHtml\Style\StyleSheet;
class HtmlTui extends Tui
{
    public function __construct(StyleSheet|NativeStyleSheet|null $styleSheet = null, ?TerminalInterface $terminal = null, ?Keybindings $keybindings = null, ?FontRegistry $fontRegistry = null, ?RendererInterface $renderer = null, ?EventDispatcherInterface $eventDispatcher = null)
    {
        parent::__construct(null, $terminal, $keybindings, $fontRegistry, $renderer ?? new Renderer($styleSheet instanceof StyleSheet ? $styleSheet : null, $fontRegistry), $eventDispatcher);
        if ($styleSheet instanceof NativeStyleSheet) {
            $this->addStyleSheet($styleSheet);
        }
    }

    public function getMediaContext(): \Symfony\Component\TuiHtml\Style\MediaContext
    {
        return $this->getRenderer()->getMediaContext();
    }

    public function setMediaContext(\Symfony\Component\TuiHtml\Style\MediaContext $media): static
    {
        $this->getRenderer()->setMediaContext($media);
        $this->requestRender();

        return $this;
    }

    public function getFragmentLinkAt(int $row, int $col): ?string
    {
        return $this->getRenderer()->getFragmentLinkAt($row, $col);
    }

    public function getLinkAt(int $row, int $col): ?string
    {
        return $this->getRenderer()->getLinkAt($row, $col);
    }

    public function getScrollControlAt(int $row, int $col): ?array
    {
        return $this->getRenderer()->getScrollControlAt($row, $col);
    }

    public function setImplicitAnchor(\Symfony\Component\Tui\Widget\AbstractWidget $widget, ?\Symfony\Component\Tui\Widget\AbstractWidget $anchor): void
    {
        $this->getRenderer()->setImplicitAnchor($widget, $anchor);
    }

    public function reveal(\Symfony\Component\Tui\Widget\AbstractWidget $widget): void
    {
        $this->getRenderer()->reveal($widget);
    }

    public function addStyleProvider(\Symfony\Component\TuiHtml\Style\StyleProviderInterface $provider): static
    {
        $this->getRenderer()->getStyleSheet()->addProvider($provider);
        $this->requestRender();

        return $this;
    }

    public function addStyleSheet(StyleSheet|NativeStyleSheet $styleSheet): static
    {
        $this->getRenderer()->addStyleSheet($styleSheet);
        $this->requestRender();

        return $this;
    }
}
