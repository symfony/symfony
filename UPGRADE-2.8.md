UPGRADE FROM 2.7 to 2.8
=======================

Form
----

 * The "checkbox" form-type now interprets '0' and '' values as unchecked unless you 
   explicitly specified one of those as checked-value (the "value"-option). 
   Any other value will transform to NULL.
   
   Before:
   ```
   true  <---> '1' (default checked-value)
   false <---> null
   (any other value were true as well)
   true  <--- ''
   true  <--- '0'
   true  <--- 'foobar'
   ```
   
   After:
   ```
   true  <---> '1' (default checked-value)
   false <---> null
   true  <---> true
   false <---> false
   false <--- ''
   false <--- '0'
   false <--- 'false'
   true  <---  '1'
   true  <---  'true'
   (any other value will cause an TransformationFailedException and results in the form-value being null)
   null  <--- 'foobar'
   ```
   
   The new behaviour is due to changes of the `BooleanToStringTransformer` where any other value
   than `trueValue`, '0' and '' will now cause a `TransformationFailedException`.
   It should ease Javascript form-submissions where a serialized form with a checkbox-type
   were interpreted as checked in the backend even if the submitted value wasn't '1' but '0', 
   which may cause confusion when checkbox is mapped to boolean-type property in the 
   JS model.
   
 * The "cascade_validation" option was deprecated. Use the "constraints"
   option together with the `Valid` constraint instead. Contrary to
   "cascade_validation", "constraints" must be set on the respective child forms,
   not the parent form.
   
   Before:
   
   ```php
   $form = $this->createForm('form', $article, array('cascade_validation' => true))
       ->add('author', new AuthorType())
       ->getForm();
   ```
   
   After:
   
   ```php
   use Symfony\Component\Validator\Constraints\Valid;
   
   $form = $this->createForm('form', $article)
       ->add('author', new AuthorType(), array(
           'constraints' => new Valid(),
       ))
       ->getForm();
   ```
   
   Alternatively, you can set the `Valid` constraint in the model itself:
   
   ```php
   use Symfony\Component\Validator\Constraints as Assert;
   
   class Article
   {
       /**
        * @Assert\Valid
        */
       private $author;
   }
   ```

Translator
----------

 * The `getMessages()` method of the `Symfony\Component\Translation\Translator` was deprecated and will be removed in
   Symfony 3.0. You should use the `getCatalogue()` method of the `Symfony\Component\Translation\TranslatorBagInterface`.

   Before:

   ```php
   $messages = $translator->getMessages();
   ```

   After:

   ```php
    $catalogue = $translator->getCatalogue($locale);
    $messages = $catalogue->all();

    while ($catalogue = $catalogue->getFallbackCatalogue()) {
        $messages = array_replace_recursive($catalogue->all(), $messages);
    }
   ```
