CHANGELOG
=========

8.1
---

 * Add `resolveType()` for customizing how types are resolved in runtimes extending `SymfonyRuntime`
 * Add support for apps returning a `Response` object in `FrankenPhpWorkerRunner`
 * Add `FRANKENPHP_RESET_KERNEL` to reset the kernel between requests

7.4
---

 * Add JSON encoded value support for `APP_RUNTIME_OPTIONS`
 * Add `FrankenPhpWorkerRunner`
 * Add automatic detection of FrankenPHP worker mode in `SymfonyRuntime`
 * Expose the runtime class in `$_SERVER['APP_RUNTIME']`
 * Expose the runtime options in `$_SERVER['APP_RUNTIME_OPTIONS']`
 * Make `project_dir` configurable in `composer.json`
 * Expose `project_dir` as `APP_PROJECT_DIR` env var

6.4
---

 * Add argument `bool $debug = false` to `HttpKernelRunner::__construct()`

5.4
---

 * The component is not experimental anymore
 * Add options "env_var_name" and "debug_var_name" to `GenericRuntime` and `SymfonyRuntime`
 * Add option "dotenv_overload" to `SymfonyRuntime`

5.3.0
-----

 * Add the component
