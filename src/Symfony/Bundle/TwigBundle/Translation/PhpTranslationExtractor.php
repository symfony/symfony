<?php

namespace Symfony\Bundle\TwigBundle\Translation;

use Symfony\Component\Translation\MessageCatalogue;

class PhpTranslationExtractor
{
    public function extractMessages($directory, MessageCatalogue $catalog)
    {
        $finder = new Finder();
        $files = $finder->files()->name('*.php')->in($directory);
        foreach ($files as $file) {
            $this->parseTokens(token_get_all(file_get_contents($file)), $catalog);
        }
    }

    protected function parseTokens($tokens, $catalog)
    {

    }
}