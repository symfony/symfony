<?php

namespace Symfony\Component\Routing\Tests\Fixtures\Matcher;

use Symfony\Component\Routing\Matcher\UrlMatcher;

class CustomUrlMatcher extends UrlMatcher
{
    public function getExpressionLanguageProviders()
    {
        return $this->expressionLanguageProviders;
    }
}
