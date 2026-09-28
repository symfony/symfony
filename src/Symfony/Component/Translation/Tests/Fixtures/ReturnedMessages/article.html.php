<?php

use Symfony\Component\Translation\Tests\Fixtures\ReturnedMessages\Article;
use Symfony\Component\Translation\Tests\Fixtures\ReturnedMessages\ArticleStatus;

echo $view['translator']->trans(ArticleStatus::Draft->label());
echo $view['translator']->trans(ArticleStatus::fromSwitch($status));
$article = new Article(ArticleStatus::Draft);
echo $view['translator']->trans($article->status->label());
echo $view['translator']->trans($article->getStatus()?->label());
echo $view['translator']->trans($article->getTitle());
echo $view['translator']->trans($article->getSummary());
echo $view['translator']->trans(Article::create()->getTitle());
echo $view['translator']->trans($article->getRecursive(false));

function article_status_label(ArticleStatus $status, $translator)
{
    return $translator->trans($status->label());
}
