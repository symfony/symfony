<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Content;

use Symfony\Bridge\TuiHtml\Render\RenderContext;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Widget\HtmlRootWidget;
use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Content\IntrinsicSize;
use Symfony\Component\HtmlRenderer\Content\ReplacedContentInterface;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;

/** @internal Independent document content; the containing engine owns iframe chrome and clipping. */
final class FrameContentAdapter implements ReplacedContentInterface
{
    public function __construct(private readonly HtmlRootWidget $root, private readonly TerminalTextMeasurer $metrics, private readonly MediaContext $media, private readonly FontRegistry $fonts)
    {
    }

    public function getIntrinsicSize(): IntrinsicSize
    {
        return new IntrinsicSize(0, 0, 0);
    }

    public function layout(float $width, float $availableHeight): ContentLayout
    {
        $columns = max(1, (int) floor($width / $this->metrics->cellWidth));
        $rows = max(1, (int) floor($availableHeight / $this->metrics->cellHeight));
        $this->root->getHtmlStyleSheet()->setScope($this->root, [$columns, $rows]);
        $this->root->getPresentation()->render($this->root, new RenderContext($columns, $rows, null, $this->fonts), $this->media->withSize($columns, $rows));
        $result = $this->root->getPresentation()->getResult();

        return new ContentLayout($result->getLayout()->bounds->height, $result->getDisplayList());
    }
}
