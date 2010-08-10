<?php
namespace Symfony\Framework\Bundle;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Components\Console\Application;
use Symfony\Components\Finder\Finder;
abstract class Bundle implements BundleInterface {
    protected $container;
    protected $name;
    protected $namespacePrefix;
    protected $path;
    protected $reflection;
    public function setContainer(ContainerInterface $container = null) {
        $this->container = $container; }
    public function boot() { }
    public function shutdown() { }
    public function getName() {
        if (null === $this->name) {
            $this->initReflection(); }
        return $this->name; }
    public function getNamespacePrefix() {
        if (null === $this->name) {
            $this->initReflection(); }
        return $this->namespacePrefix; }
    public function getPath() {
        if (null === $this->name) {
            $this->initReflection(); }
        return $this->path; }
    public function getReflection() {
        if (null === $this->name) {
            $this->initReflection(); }
        return $this->reflection; }
    public function registerExtensions(ContainerBuilder $container) {
        if (!$dir = realpath($this->getPath().'/DependencyInjection')) {
            return array(); }
        $finder = new Finder();
        $finder->files()->name('*Extension.php')->in($dir);
        $prefix = $this->namespacePrefix.'\\'.$this->name.'\\DependencyInjection';
        foreach ($finder as $file) {
            $class = $prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.basename($file, '.php');
            if ('Extension' === substr($class, -9)) {
                $container->registerExtension(new $class()); } } }
    public function registerCommands(Application $application) {
        if (!$dir = realpath($this->getPath().'/Command')) {
            return; }
        $finder = new Finder();
        $finder->files()->name('*Command.php')->in($dir);
        $prefix = $this->namespacePrefix.'\\'.$this->name.'\\Command';
        foreach ($finder as $file) {
            $r = new \ReflectionClass($prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.basename($file, '.php'));
            if ($r->isSubclassOf('Symfony\\Components\\Console\\Command\\Command') && !$r->isAbstract()) {
                $application->addCommand($r->newInstance()); } } }
    protected function initReflection() {
        $tmp = dirname(str_replace('\\', '/', get_class($this)));
        $this->namespacePrefix = str_replace('/', '\\', dirname($tmp));
        $this->name = basename($tmp);
        $this->reflection = new \ReflectionObject($this);
        $this->path = dirname($this->reflection->getFilename()); } }
namespace Symfony\Framework\Bundle;
use Symfony\Components\DependencyInjection\ContainerInterface;
interface BundleInterface {
    public function boot();
    public function shutdown();
    public function setContainer(ContainerInterface $container); }
namespace Symfony\Framework;
use Symfony\Framework\Bundle\Bundle;
use Symfony\Framework\ClassCollectionLoader;
class KernelBundle extends Bundle {
    public function boot() {
        if ($this->container->has('error_handler')) {
            $this->container['error_handler']; } } }
namespace Symfony\Framework\DependencyInjection;
use Symfony\Components\DependencyInjection\Extension\Extension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\ContainerBuilder;
class KernelExtension extends Extension {
    public function testLoad($config, ContainerBuilder $container) {
        $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $loader->load('test.xml'); }
    public function sessionLoad($config, ContainerBuilder $container) {
        if (!$container->hasDefinition('session')) {
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('session.xml'); }
        if (isset($config['default_locale'])) {
            $container->setParameter('session.default_locale', $config['default_locale']); }
        if (isset($config['class'])) {
            $container->setParameter('session.class', $config['class']); }
        foreach (array('name', 'lifetime', 'path', 'domain', 'secure', 'httponly', 'cache_limiter', 'pdo.db_table') as $name) {
            if (isset($config['session'][$name])) {
                $container->setParameter('session.options.'.$name, $config['session'][$name]); } }
        if (isset($config['session']['class'])) {
            $class = $config['session']['class'];
            if (in_array($class, array('Native', 'Pdo'))) {
                $class = 'Symfony\\Components\\HttpFoundation\\SessionStorage\\'.$class.'SessionStorage'; }
            $container->setParameter('session.session', 'session.session.'.strtolower($class)); } }
    public function configLoad($config, ContainerBuilder $container) {
        if (!$container->hasDefinition('event_dispatcher')) {
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('services.xml');
            if ($container->getParameter('kernel.debug')) {
                $loader->load('debug.xml');
                $container->setDefinition('event_dispatcher', $container->findDefinition('debug.event_dispatcher')); } }
        if (isset($config['charset'])) {
            $container->setParameter('kernel.charset', $config['charset']); }
        if (array_key_exists('error_handler', $config)) {
            if (false === $config['error_handler']) {
                $container->getDefinition('error_handler')->setMethodCalls(array()); } else {
                $container->getDefinition('error_handler')->addMethodCall('register', array());
                $container->setParameter('error_handler.level', $config['error_handler']); } } }
    public function getXsdValidationBasePath() {
        return false; }
    public function getNamespace() {
        return 'http://www.symfony-project.org/schema/dic/symfony/kernel'; }
    public function getAlias() {
        return 'kernel'; } }
namespace Symfony\Framework\Debug;
class ErrorHandler {
    protected $levels = array(
        E_WARNING           => 'Warning',
        E_NOTICE            => 'Notice',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
    );
    protected $level;
    public function __construct($level = null) {
        $this->level = null === $level ? error_reporting() : $level; }
    public function register() {
        set_error_handler(array($this, 'handle')); }
    public function handle($level, $message, $file, $line, $context) {
        if (0 === $this->level) {
            return false; }
        if (error_reporting() & $level && $this->level & $level) {
            throw new \ErrorException(sprintf('%s: %s in %s line %d', isset($this->levels[$level]) ? $this->levels[$level] : $level, $message, $file, $line)); }
        return false; } }
namespace Symfony\Framework;
class ClassCollectionLoader {
    static public function load($classes, $cacheDir, $name, $autoReload) {
        $classes = array_unique($classes);
        $cache = $cacheDir.'/'.$name.'.php';
                $reload = false;
        if ($autoReload) {
            $metadata = $cacheDir.'/'.$name.'.meta';
            if (!file_exists($metadata) || !file_exists($cache)) {
                $reload = true; } else {
                $time = filemtime($cache);
                $meta = unserialize(file_get_contents($metadata));
                if ($meta[1] != $classes) {
                    $reload = true; } else {
                    foreach ($meta[0] as $resource) {
                        if (!file_exists($resource) || filemtime($resource) > $time) {
                            $reload = true;
                            break; } } } } }
        if (!$reload && file_exists($cache)) {
            require_once $cache;
            return; }
        $files = array();
        $content = '';
        foreach ($classes as $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                throw new \InvalidArgumentException(sprintf('Unable to load class "%s"', $class)); }
            $r = new \ReflectionClass($class);
            $files[] = $r->getFileName();
            $content .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($r->getFileName())); }
                if (!is_dir(dirname($cache))) {
            mkdir(dirname($cache), 0777, true); }
        self::writeCacheFile($cache, Kernel::stripComments('<?php '.$content));
        if ($autoReload) {
                        self::writeCacheFile($metadata, serialize(array($files, $classes))); } }
    static protected function writeCacheFile($file, $content) {
        $tmpFile = tempnam(dirname($file), basename($file));
        if (!$fp = @fopen($tmpFile, 'wb')) {
            die(sprintf('Failed to write cache file "%s".', $tmpFile)); }
        @fwrite($fp, $content);
        @fclose($fp);
        if ($content != file_get_contents($tmpFile)) {
            die(sprintf('Failed to write cache file "%s" (cache corrupted).', $tmpFile)); }
        if (!@rename($tmpFile, $file)) {
            throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file)); }
        chmod($file, 0644); } }
namespace Symfony\Framework;
use Symfony\Components\EventDispatcher\EventDispatcher as BaseEventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\DependencyInjection\ContainerInterface;
class EventDispatcher extends BaseEventDispatcher {
    public function __construct(ContainerInterface $container) {
        foreach ($container->findTaggedServiceIds('kernel.listener') as $id => $attributes) {
            $container->get($id)->register($this); } } }
