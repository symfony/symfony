<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Extractor\ExtractorInterface;

/**
 * PhpExtractor extracts translation messages from a PHP template.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
class PhpExtractor implements ExtractorInterface
{
    const MESSAGE_TOKEN = 300;
    const IGNORE_TOKEN = 400;

    /**
     * Prefix for new found message.
     *
     * @var string
     */
    private $prefix = '';

    /**
     * The sequence that captures translation messages.
     *
     * @var array
     */
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

    protected $whitespaceTokens = array(T_WHITESPACE);
    protected $messageTokens = array(T_START_HEREDOC, T_WHITESPACE, T_STRING, T_END_HEREDOC, T_ENCAPSED_AND_WHITESPACE, T_CONSTANT_ENCAPSED_STRING);

    /**
     * {@inheritdoc}
     */
    public function extract($directory, MessageCatalogue $catalog)
    {
        // load any existing translation files
        $finder = new Finder();
        $files = $finder->files()->name('*.php')->in($directory);
        foreach ($files as $file) {
            $this->parseTokens(token_get_all(file_get_contents($file)), $catalog);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Normalizes a token.
     *
     * @param  mixed  $token
     * @return string
     */
    protected function normalizeToken($token)
    {
        if (is_array($token)) {
            return $token[1];
        }

        return $token;
    }

    /**
     * Seeks to a non-whitespace token
     *
     * @param \ArrayIterator $tokenIterator
     */
    protected function seekToNextRelaventToken($tokenIterator)
    {
        $t = $tokenIterator->current();
        while (is_array($t) && in_array($t[0], $this->whitespaceTokens)) {
            $tokenIterator->next();
            $t = $tokenIterator->current();
        }
    }

    /**
     * Extracts the message from the iterator while the tokens
     * match allowed message tokens
     *
     * @param \ArrayIterator $tokenIterator
     */
    protected function getMessage($tokenIterator)
    {
        $message = '';
        $t = $tokenIterator->current();
        while (is_array($t) && in_array($t[0], $this->messageTokens)) {
            $message .= $this->normalizeToken($t);
            $tokenIterator->next();
            $t = $tokenIterator->current();
        }

        return $message;
    }

    /**
     * Extracts trans message from PHP tokens.
     *
     * @param array            $tokens
     * @param MessageCatalogue $catalog
     */
    protected function parseTokens($tokens, MessageCatalogue $catalog)
    {
        $tokenIterator = new \ArrayIterator($tokens);

        for ($key = 0; $key < $tokenIterator->count(); $key++) {
            foreach ($this->sequences as $sequence) {
                $message = '';
                $tokenIterator->seek($key);
                foreach ($sequence as $id => $item) {

                    $this->seekToNextRelaventToken($tokenIterator);

                    if ($this->normalizeToken($tokenIterator->current()) == $item) {
                        $tokenIterator->next();
                        continue;
                    } elseif (self::MESSAGE_TOKEN == $item) {
                        $message = $this->getMessage($tokenIterator);
                    } else {
                        break;
                    }
                }

                if ($message) {
                    // need to eval here because
                    // the $message we have here is a PHP string, complete with quotes
                    // escaped characters, etc
                    $msg = eval("return $message;");
                    $catalog->set($msg, $this->prefix.$msg);
                    break;
                }
            }
        }
    }
}
