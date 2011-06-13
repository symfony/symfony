<?php

namespace Symfony\Bundle\TwigBundle\Translation;

use Symfony\Component\Translation\MessageCatalogue;

class PhpTranslationExtractor
{
    const MESSAGE_TOKEN = 300;
    const IGNORE_TOKEN = 400;

    protected $sequences = array(
        array(
            '$view',
            '[',
            '\'translator\'',
            ']',
            '->',
            'trans',
            '(',
            self::MESSAGE_TOKEN,
			')',
        ),
    );

    public function extractMessages($directory, MessageCatalogue $catalog)
    {
        $finder = new Finder();
        $files = $finder->files()->name('*.php')->in($directory);
        foreach ($files as $file) {
            $this->parseTokens(token_get_all(file_get_contents($file)), $catalog);
        }
    }

    protected function normalizeToken($token)
    {
        if(is_array($token)) {

            return $token[1];
        }
        else {

            return $token;
        }
    }

    protected function parseTokens($tokens, $catalog)
    {
        foreach ($tokens as $key => $token) {

            foreach ($this->sequences as $sequence) {

                $message = '';

                foreach ($sequence as $id => $item) {

                    if($this->normalizeToken($tokens[$key + $id]) == $item) {
                        continue;
                    }
                    elseif (self::MESSAGE_TOKEN == $item) {

                        $message = $this->normalizeToken($tokens[$key + $id]);
                    }
                    elseif(self::IGNORE_TOKEN == $item) {

                        continue;
                    }
                    else {
                        break;
                    }
                }

                if($message) {

                    $catalog->set($message, $message);
                    break;
                }
            }
        }
    }
}