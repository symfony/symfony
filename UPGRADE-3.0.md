UPGRADE FROM 2.x to 3.0
=======================

### ClassLoader

 * The `UniversalClassLoader` class has been removed in favor of
   `ClassLoader`. The only difference is that some method names are different:

      * `registerNamespaces()` -> `addPrefixes()`
      * `registerPrefixes()` -> `addPrefixes()`
      * `registerNamespaces()` -> `addPrefix()`
      * `registerPrefixes()` -> `addPrefix()`
      * `getNamespaces()` -> `getPrefixes()`
      * `getNamespaceFallbacks()` -> `getFallbackDirs()`
      * `getPrefixFallbacks()` -> `getFallbackDirs()`

 * The `DebugUniversalClassLoader` class has been removed in favor of
   `DebugClassLoader`. The difference is that the constructor now takes a
   loader to wrap.

### Form

 * Passing a `Symfony\Component\HttpFoundation\Request` instance to
   `FormInterface::bind()` was disabled. You should use
   `FormInterface::process()` instead.

   Before:

   ```
   if ('POST' === $request->getMethod()) {
       $form->bind($request);

       if ($form->isValid()) {
           // ...
       }
   }
   ```

   After:

   ```
   if ($form->process($request)->isValid()) {
       // ...
   }
   ```

   If you want to test whether the form was submitted separately, you can use
   the method `isBound()`:

   ```
   if ($form->process($request)->isBound()) {
      // ...

      if ($form->isValid()) {
          // ...
      }
   }
   ```

### FrameworkBundle

 * The `enctype` method of the `form` helper was removed. You should use the
   new method `start` instead.

   Before:

   ```
   <form method="post" action="http://example.com" <?php echo $view['form']->enctype($form) ?>>
       ...
   </form>
   ```

   After:

   ```
   <?php echo $view['form']->start($form) ?>
       ...
   <?php echo $view['form']->end($form) ?>
   ```

   The method and action of the form default to "POST" and the current
   document. If you want to change these values, you can set them explicitly in
   the controller.

   Alternative 1:

   ```
   $form = $this->createForm('my_form', $formData, array(
       'method' => 'PUT',
       'action' => $this->generateUrl('target_route'),
   ));
   ```

   Alternative 2:

   ```
   $form = $this->createFormBuilder($formData)
       // ...
       ->setMethod('PUT')
       ->setAction($this->generateUrl('target_route'))
       ->getForm();
   ```

   It is also possible to override the method and the action in the template:

   ```
   <?php echo $view['form']->start($form, array('method' => 'GET', 'action' => 'http://example.com')) ?>
       ...
   <?php echo $view['form']->end($form) ?>
   ```

### HttpKernel

 * The `Symfony\Component\HttpKernel\Log\LoggerInterface` has been removed in
   favor of `Psr\Log\LoggerInterface`. The only difference is that some method
   names are different:

     * `emerg()` -> `emergency()`
     * `crit()`  -> `critical()`
     * `err()`   -> `error()`
     * `warn()`  -> `warning()`

   The previous method renames also happened to the following classes:

     * `Symfony\Bridge\Monolog\Logger`
     * `Symfony\Component\HttpKernel\Log\NullLogger`

 * The `Symfony\Component\HttpKernel\Kernel::init()` method has been removed.

 * The following classes have been renamed as they have been moved to the
   Debug component:

    * `Symfony\Component\HttpKernel\Debug\ErrorHandler` -> `Symfony\Component\Debug\ErrorHandler`
    * `Symfony\Component\HttpKernel\Debug\ExceptionHandler` -> `Symfony\Component\Debug\ExceptionHandler`
    * `Symfony\Component\HttpKernel\Exception\FatalErrorException` -> `Symfony\Component\Debug\Exception\FatalErrorException`
    * `Symfony\Component\HttpKernel\Exception\FlattenException` -> `Symfony\Component\Debug\Exception\FlattenException`

### Routing

 * Some route settings have been renamed:

     * The `pattern` setting for a route has been deprecated in favor of `path`
     * The `_scheme` and `_method` requirements have been moved to the `schemes` and `methods` settings

   Before:

   ```
   article_edit:
       pattern: /article/{id}
       requirements: { '_method': 'POST|PUT', '_scheme': 'https', 'id': '\d+' }

   <route id="article_edit" pattern="/article/{id}">
       <requirement key="_method">POST|PUT</requirement>
       <requirement key="_scheme">https</requirement>
       <requirement key="id">\d+</requirement>
   </route>

   $route = new Route();
   $route->setPattern('/article/{id}');
   $route->setRequirement('_method', 'POST|PUT');
   $route->setRequirement('_scheme', 'https');
   ```

   After:

   ```
   article_edit:
       path: /article/{id}
       methods: [POST, PUT]
       schemes: https
       requirements: { 'id': '\d+' }

   <route id="article_edit" path="/article/{id}" methods="POST PUT" schemes="https">
       <requirement key="id">\d+</requirement>
   </route>

   $route = new Route();
   $route->setPath('/article/{id}');
   $route->setMethods(array('POST', 'PUT'));
   $route->setSchemes('https');
   ```

### Translator

 * The `Translator::setFallbackLocale()` method has been removed in favor of
   `Translator::setFallbackLocales()`.

### Twig Bridge

 * The `render` tag is deprecated in favor of the `render` function.

 * The `form_enctype` helper was removed. You should use the new `form_start`
   function instead.

   Before:

   ```
   <form method="post" action="http://example.com" {{ form_enctype(form) }}>
       ...
   </form>
   ```

   After:

   ```
   {{ form_start(form) }}
       ...
   {{ form_end(form) }}
   ```

   The method and action of the form default to "POST" and the current
   document. If you want to change these values, you can set them explicitly in
   the controller.

   Alternative 1:

   ```
   $form = $this->createForm('my_form', $formData, array(
       'method' => 'PUT',
       'action' => $this->generateUrl('target_route'),
   ));
   ```

   Alternative 2:

   ```
   $form = $this->createFormBuilder($formData)
       // ...
       ->setMethod('PUT')
       ->setAction($this->generateUrl('target_route'))
       ->getForm();
   ```

   It is also possible to override the method and the action in the template:

   ```
   {{ form_start(form, {'method': 'GET', 'action': 'http://example.com'}) }}
       ...
   {{ form_end(form) }}
   ```

### Yaml

 * The ability to pass file names to `Yaml::parse()` has been removed.

   Before:

   ```
   Yaml::parse($fileName);
   ```

   After:

   ```
   Yaml::parse(file_get_contents($fileName));
   ```
