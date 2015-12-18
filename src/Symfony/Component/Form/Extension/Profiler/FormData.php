<?php

namespace Symfony\Component\Form\Extension\Profiler;

use Symfony\Component\Profiler\ProfileData\ProfileDataInterface;

class FormData implements ProfileDataInterface
{
    private $forms;
    private $nbErrors;

    public function __construct(array $forms, $nbErrors)
    {
        $this->forms = $forms;
        $this->nbErrors = $nbErrors;
    }

    public function getForms()
    {
        return $this->forms;
    }

    public function getNbErrors()
    {
        return $this->nbErrors;
    }

    /**
     * Returns the name of the collector.
     *
     * @return string The collector name
     *
     * @api
     */
    public function getName()
    {
        return 'form';
    }

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return serialize(array('forms' => $this->forms, 'nbErrors' => $this->nbErrors));
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);
        $this->forms = $unserialized['forms'];
        $this->nbErrors = $unserialized['nbErrors'];
    }
}