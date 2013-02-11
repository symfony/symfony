UPGRADE FROM 2.x to 3.0
=======================

### ClassLoader

 * The `UniversalClassLoader` class has been removed in favor of `ClassLoader`. The only difference is that some method
    names are different:

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

### Twig Bridge

 * The `render` tag is deprecated in favor of the `render` function.

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
