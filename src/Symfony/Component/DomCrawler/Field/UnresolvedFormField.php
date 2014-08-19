<?php

namespace Symfony\Component\DomCrawler\Field;

use Symfony\Component\DomCrawler\FormFieldRegistry;

class UnresolvedFormField extends FormField
{
    /**
     * @param FormFieldRegistry $registry
     * @param $name
     */
    public function __construct(FormFieldRegistry $registry, $name)
    {
        $this->registry = $registry;
        $this->name = $name;
    }

    public function setValue($value)
    {
        $doc = new \DOMDocument("1.0");
        $element = $doc->createElement('input');
        $element->setAttribute('type', 'text');
        $element->setAttribute('name', $this->name);

        $field = new InputFormField($element);
        $field->setValue($value);

        $this->registry->add($field);
    }

    public function select($value)
    {
        $doc = new \DOMDocument("1.0");
        $element = $doc->createElement('select');
        $element->setAttribute('name', $this->name);
        $element->setAttribute('multiple', true);

        $field = new ChoiceFormField($element);
        $field->disableValidation();
        $field->select($value);

        $this->registry->add($field);
    }

    public function tick()
    {
        $field = $this->createCheckboxFormField();
        $field->tick();
    }

    public function untick()
    {
        $field = $this->createCheckboxFormField();
        $field->untick();
    }

    public function upload($filename)
    {
        $doc = new \DOMDocument("1.0");
        $element = $doc->createElement('input');
        $element->setAttribute('name', $this->name);
        $element->setAttribute('type', 'file');

        $field = new FileFormField($element);
        $field->upload($filename);

        $this->registry->add($field);
    }

    public function isDisabled()
    {
        return false;
    }

    private function createCheckboxFormField()
    {
        $doc = new \DOMDocument("1.0");
        $element = $doc->createElement('input');
        $element->setAttribute('name', $this->name);
        $element->setAttribute('type', 'checkbox');

        $field = new ChoiceFormField($element);
        $field->disableValidation();

        $this->registry->add($field);

        return $field;
    }

    /**
     * Initializes the form field.
     */
    protected function initialize()
    {
        // Do nothing
    }
}
