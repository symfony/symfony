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
 * PhpExtractor extracts translation messages from a php template.
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

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Normalizes a token.
     *
     * @param mixed $token
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
     * Extracts trans message from php tokens.
     *
     * @param array            $tokens
     * @param MessageCatalogue $catalog
     */
    protected function parseTokens($tokens, MessageCatalogue $catalog)
    {
        foreach ($tokens as $key => $token) {
            foreach ($this->sequences as $sequence) {
                $message = '';

                foreach ($sequence as $id => $item) {
                    if ($this->normalizeToken($tokens[$key + $id]) == $item) {
                        continue;
                    } elseif (self::MESSAGE_TOKEN == $item) {
                        $message = $this->normalizeToken($tokens[$key + $id]);
                    } elseif (self::IGNORE_TOKEN == $item) {
                        continue;
                    } else {
                        break;
                    }
                }

                $message = trim($message, '\'');

                if ($message) {
                    $catalog->set($message, $this->prefix.$message);
                    break;
                }
            }
        }
    }
}
