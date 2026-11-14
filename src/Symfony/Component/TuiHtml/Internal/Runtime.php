<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Internal;

use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Render\Renderer;
use Symfony\Component\TuiHtml\Style\StyleProviderInterface;

/** @internal */
final class Runtime
{
    public static function find(?Tui $tui): ?self
    {
        return null === $tui ? null : self::of($tui);
    }

    public static function of(Tui $tui): self
    {
        $renderer = $tui->getRenderer();
        if (!$renderer instanceof Renderer) {
            throw new \Symfony\Component\Tui\Exception\LogicException('Select the TuiHtml renderer before mounting an HTML view.');
        }
        $renderer->bindRuntime($tui);

        return new self($tui, $renderer);
    }

    public function addStyleProvider(StyleProviderInterface $provider): void
    {
        $this->renderer->getStyleSheet()->addProvider($provider);
        $this->tui->requestRender();
    }

    public function removeStyleProvider(StyleProviderInterface $provider): void
    {
        $this->renderer->getStyleSheet()->removeProvider($provider);
        $this->tui->requestRender();
    }

    public function reveal(AbstractWidget $widget): void
    {
        $this->renderer->reveal($widget);
        $this->tui->requestRender();
    }

    public function setImplicitAnchor(AbstractWidget $widget, ?AbstractWidget $anchor): void
    {
        $this->renderer->setImplicitAnchor($widget, $anchor);
    }

    public function getFragmentLinkAt(int $row, int $col): ?string
    {
        return $this->renderer->getFragmentLinkAt($row, $col);
    }

    public function getLinkAt(int $row, int $col): ?string
    {
        return $this->renderer->getLinkAt($row, $col);
    }

    public function getScrollControlAt(int $row, int $col): ?array
    {
        return $this->renderer->getScrollControlAt($row, $col);
    }

    private function __construct(private Tui $tui, private Renderer $renderer)
    {
    }
}
