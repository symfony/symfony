<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\NodeVisitor\TranslationDefaultDomainNodeVisitor;
use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;
use Symfony\Bridge\Twig\TokenParser\TransChoiceTokenParser;
use Symfony\Bridge\Twig\TokenParser\TransDefaultDomainTokenParser;
use Symfony\Bridge\Twig\TokenParser\TransTokenParser;
use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;
use Twig\Extension\AbstractExtension;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TwigFilter;

/**
 * Provides integration of the Translation component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since Symfony 4.2
 */
class TranslationExtension extends AbstractExtension
{
    private $translator;
    private $translationNodeVisitor;

    /**
     * @param TranslatorInterface|null $translator
     */
    public function __construct($translator = null, NodeVisitorInterface $translationNodeVisitor = null)
    {
        if (null !== $translator && !$translator instanceof LegacyTranslatorInterface && !$translator instanceof TranslatorInterface) {
            throw new \TypeError(sprintf('Argument 1 passed to %s() must be an instance of %s, %s given.', __METHOD__, TranslatorInterface::class, \is_object($translator) ? \get_class($translator) : \gettype($translator)));
        }
        $this->translator = $translator;
        $this->translationNodeVisitor = $translationNodeVisitor;
    }

    /**
     * @return TranslatorInterface|null
     */
    public function getTranslator()
    {
        if (null === $this->translator) {
            if (!interface_exists(TranslatorInterface::class)) {
                throw new \LogicException(sprintf('You cannot use the "%s" if the Translation Contracts are not available. Try running "composer require symfony/translation".', __CLASS__));
            }

            $this->translator = new class() implements TranslatorInterface {
                use TranslatorTrait;
            };
        }

        return $this->translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('trans', [$this, 'trans']),
            new TwigFilter('transchoice', [$this, 'transchoice'], ['deprecated' => '4.2', 'alternative' => 'trans" with parameter "%count%']),
        ];
    }

    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return AbstractTokenParser[]
     */
    public function getTokenParsers()
    {
        return [
            // {% trans %}Symfony is great!{% endtrans %}
            new TransTokenParser(),

            // {% transchoice count %}
            //     {0} There is no apples|{1} There is one apple|]1,Inf] There is {{ count }} apples
            // {% endtranschoice %}
            new TransChoiceTokenParser(),

            // {% trans_default_domain "foobar" %}
            new TransDefaultDomainTokenParser(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeVisitors()
    {
        return [$this->getTranslationNodeVisitor(), new TranslationDefaultDomainNodeVisitor()];
    }

    public function getTranslationNodeVisitor()
    {
        return $this->translationNodeVisitor ?: $this->translationNodeVisitor = new TranslationNodeVisitor();
    }

    public function trans($message, array $arguments = [], $domain = null, $locale = null, $count = null)
    {
        if (null !== $count) {
            $arguments['%count%'] = $count;
        }

        return $this->getTranslator()->trans($message, $arguments, $domain, $locale);
    }

    /**
     * @deprecated since Symfony 4.2, use the trans() method instead with a %count% parameter
     */
    public function transchoice($message, $count, array $arguments = [], $domain = null, $locale = null)
    {
        $translator = $this->getTranslator();

        if ($translator instanceof TranslatorInterface) {
            return $translator->trans($message, array_merge(['%count%' => $count], $arguments), $domain, $locale);
        }

        return $translator->transChoice($message, $count, array_merge(['%count%' => $count], $arguments), $domain, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'translator';
    }
}
