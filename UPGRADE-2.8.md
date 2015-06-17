UPGRADE FROM 2.7 to 2.8
=======================

Form
----

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
