<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Accepts several messages and returns the first translated one in the list.
 *
 * A message is considered *not* translated when its translation is the same as its id.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AltTranslator implements TranslatorInterface
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     *
     * @param string|string[]|null $id
     */
    public function trans($id, array $parameters = [], string $domain = null, string $locale = null)
    {
        if ('' === $id || [] === $ids = (array) $id) {
            return '';
        }

        foreach ($ids as $id) {
            if ($id !== $message = $this->translator->trans($id, $parameters, $domain, $locale)) {
                break;
            }
        }

        return $message;
    }
}
