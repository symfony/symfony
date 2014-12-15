<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Exception\BadMethodCallException;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Simple translator implementation that simply replaces the parameters in
 * the message IDs.
 *
 * Example usage:
 *
 *     $translator = new DefaultTranslator();
 *
 *     echo $translator->trans(
 *         'This is a {{ var }}.',
 *         array('{{ var }}' => 'donkey')
 *     );
 *
 *     // -> This is a donkey.
 *
 *     echo $translator->transChoice(
 *         'This is {{ count }} donkey.|These are {{ count }} donkeys.',
 *         3,
 *         array('{{ count }}' => 'three')
 *     );
 *
 *     // -> These are three donkeys.
 *
 * This translator does not support message catalogs, translation domains or
 * locales. Instead, it implements a subset of the capabilities of
 * {@link \Symfony\Component\Translation\Translator} and can be used in places
 * where translation is not required by default but should be optional.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DefaultTranslator implements TranslatorInterface
{
    /**
     * Interpolates the given message.
     *
     * Parameters are replaced in the message in the same manner that
     * {@link strtr()} uses.
     *
     * Example usage:
     *
     *     $translator = new DefaultTranslator();
     *
     *     echo $translator->trans(
     *         'This is a {{ var }}.',
     *         array('{{ var }}' => 'donkey')
     *     );
     *
     *     // -> This is a donkey.
     *
     * @param string $id         The message id
     * @param array  $parameters An array of parameters for the message
     * @param string $domain     Ignored
     * @param string $locale     Ignored
     *
     * @return string The interpolated string
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        return strtr($id, $parameters);
    }

    /**
     * Interpolates the given choice message by choosing a variant according to a number.
     *
     * The variants are passed in the message ID using the format
     * "<singular>|<plural>". "<singular>" is chosen if the passed $number is
     * exactly 1. "<plural>" is chosen otherwise.
     *
     * This format is consistent with the format supported by
     * {@link \Symfony\Component\Translation\Translator}, but it does not
     * have the same expressiveness. While Translator supports intervals in
     * message translations, which are needed for languages other than English,
     * this translator does not. You should use Translator or a custom
     * implementation of {@link TranslatorInterface} if you need this or similar
     * functionality.
     *
     * Example usage:
     *
     *     echo $translator->transChoice(
     *         'This is {{ count }} donkey.|These are {{ count }} donkeys.',
     *         0,
     *         array('{{ count }}' => 0)
     *     );
     *
     *     // -> These are 0 donkeys.
     *
     *     echo $translator->transChoice(
     *         'This is {{ count }} donkey.|These are {{ count }} donkeys.',
     *         1,
     *         array('{{ count }}' => 1)
     *     );
     *
     *     // -> This is 1 donkey.
     *
     *     echo $translator->transChoice(
     *         'This is {{ count }} donkey.|These are {{ count }} donkeys.',
     *         3,
     *         array('{{ count }}' => 3)
     *     );
     *
     *     // -> These are 3 donkeys.
     *
     * @param string $id         The message id
     * @param int    $number     The number to use to find the index of the message
     * @param array  $parameters An array of parameters for the message
     * @param string $domain     Ignored
     * @param string $locale     Ignored
     *
     * @return string The translated string
     *
     * @throws InvalidArgumentException If the message id does not have the format
     *                                  "singular|plural".
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        $ids = explode('|', $id);

        if (1 == $number) {
            return strtr($ids[0], $parameters);
        }

        if (!isset($ids[1])) {
            throw new InvalidArgumentException(sprintf('The message "%s" cannot be pluralized, because it is missing a plural (e.g. "There is one apple|There are %%count%% apples").', $id));
        }

        return strtr($ids[1], $parameters);
    }

    /**
     * Not supported.
     *
     * @param string $locale The locale
     *
     * @throws BadMethodCallException
     */
    public function setLocale($locale)
    {
        throw new BadMethodCallException('Unsupported method.');
    }

    /**
     * Returns the locale of the translator.
     *
     * @return string Always returns 'en'
     */
    public function getLocale()
    {
        return 'en';
    }
}
