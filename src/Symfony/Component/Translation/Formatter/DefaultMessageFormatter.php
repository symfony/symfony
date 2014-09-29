<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Formatter;

/**
 * DefaultMessageFormatter.
 *
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 *
 * @api
 */
class DefaultMessageFormatter implements MessageFormatterInterface
{
    /**
     * @var MessageSelector
     */
    protected $selector;

    /**
     * Constructor.
     *
     * @param MessageSelector $selector Message selector to choose pluralization messages.
     */
    public function __construct(MessageSelector $selector = null)
    {
        $this->selector = $selector ?: new MessageSelector();
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function format($locale, $id, $number = null, array $arguments = array())
    {
        $pattern = ($number !== null)
            ? $this->selector->choose($id, (int) $number, $locale)
            : $id;

        return strtr($pattern, $arguments);
    }
}
