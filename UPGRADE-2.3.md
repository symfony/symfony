UPGRADE FROM 2.2 to 2.3
=======================

### HttpKernel

 * The `init()` method has to be called after Kernel instanciation.

   Before:

   ```
   $kernel = new AppKernel('dev', true);
   ```

   After:

   ```
   $kernel = new AppKernel('dev', true);
   $kernel->init();
   ```
