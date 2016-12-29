<?php

namespace Symfony\Component\Security\Core\Authorization\Voter\Result;

interface VoterResultInterface
{
    /**
     * @return string|null
     */
    public function getMessage();

    /**
     * @return string|null
     */
    public function getAttribute();

    /**
     * @return int
     */
    public function getResult();

    /**
     * @return array
     */
    public function getParameters();

    /**
     * @return null|string
     */
    public function getTranslationDomain();

    /**
     * @return int
     */
    public function getPlural();

    /**
     * @return VoterResultInterface|null
     */
    public function getPrevious();

    /**
     * Sets a parameter to be inserted into the result message.
     *
     * @param string $key   The name of the parameter
     * @param string $value The value to be inserted in the parameter's place
     *
     * @return $this
     */
    public function setParameter($key, $value);

    /**
     * Sets all parameters to be inserted into the result message.
     *
     * @param array $parameters An array with the parameter names as keys and
     *                          the values to be inserted in their place as
     *                          values
     *
     * @return $this
     */
    public function setParameters(array $parameters);

    /**
     * Sets the translation domain which should be used for translating the
     * result message.
     *
     * @param string $translationDomain The translation domain
     *
     * @return $this
     *
     * @see \Symfony\Component\Translation\TranslatorInterface
     */
    public function setTranslationDomain($translationDomain);

    /**
     * Sets the number which determines how the plural form of the result
     * message is chosen when it is translated.
     *
     * @param int $number The number for determining the plural form
     *
     * @return $this
     *
     * @see \Symfony\Component\Translation\TranslatorInterface::transChoice()
     */
    public function setPlural($number);

    /**
     * @param VoterResultInterface|null $voterResult
     *
     * @return $this
     */
    public function setPrevious(VoterResultInterface $voterResult = null);
}
