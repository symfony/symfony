<?php

namespace Symfony\Component\Security\Core\Authorization\Voter\Result;

class VoterResult implements VoterResultInterface
{
    /**
     * @var int
     */
    private $result;

    /**
     * @var string|null
     */
    private $attribute;

    /**
     * @var string|null
     */
    private $message;

    /**
     * @var array
     */
    private $parameters = array();

    /**
     * @var string|null
     */
    private $translationDomain;

    /**
     * @var int|null
     */
    private $plural;

    /**
     * @var VoterResultInterface|null
     */
    private $previous;

    /**
     * @param int         $result
     * @param string|null $attribute
     * @param string|null $message
     */
    public function __construct($result, $attribute = null, $message = null)
    {
        $this->result = $result;
        $this->attribute = $attribute;
        $this->message = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslationDomain()
    {
        return $this->translationDomain;
    }

    /**
     * {@inheritdoc}
     */
    public function getPlural()
    {
        return $this->plural;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrevious()
    {
        return $this->previous;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($key, $value)
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters)
    {
        foreach ($parameters as $key => $value) {
            $this->setParameter($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTranslationDomain($translationDomain)
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPlural($number)
    {
        $this->plural = $number;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPrevious(VoterResultInterface $previous = null)
    {
        $this->previous = $previous;

        return $this;
    }
}
