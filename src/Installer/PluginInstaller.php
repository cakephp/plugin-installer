<?php
namespace Cake\Composer\Installer;

use Cake\Core\Configure\Engine\PhpConfig;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem;
use RuntimeException;

class PluginInstaller extends LibraryInstaller
{
    /**
     * A flag to check usage - once
     *
     * @var bool
     */
    protected static $checkUsage = true;

    /**
     * Check usage upon construction
     *
     * @param IOInterface $io
     * @param Composer    $composer
     * @param string      $type
     * @param Filesystem  $filesystem
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library', Filesystem $filesystem = null)
    {
        parent::__construct($io, $composer, $type, $filesystem);
        $this->checkUsage($composer);
    }

    /**
     * Check that the root composer.json file use the post-autoload-dump hook
     *
     * If not, warn the user they need to update their application's composer file.
     * Do nothing if the main project is not a project (if it's a plugin in development).
     *
     * @param Composer $composer
     * @return void
     */
    public function checkUsage(Composer $composer)
    {
        if (static::$checkUsage === false) {
            return;
        }
        static::$checkUsage = false;

        $root = $composer->getPackage();

        if (!$root || $root->getType() !== 'project') {
            return;
        }

        $scripts = $composer->getPackage()->getScripts();
        $postAutoloadDump = 'Cake\Composer\Installer\PluginInstaller::postAutoloadDump';
        if (
            !isset($scripts['post-autoload-dump']) ||
            !in_array($postAutoloadDump, $scripts['post-autoload-dump'])
        ) {
            $this->warnUpdateRequired();
        }
    }

    /**
     * Warn the developer they need to update their root composer.json file
     *
     * @return void
     */
    public function warnUpdateRequired()
    {
        $emptyLine = sprintf('<error>%s</error>', str_repeat(' ', 80));

        $messages = [
            '',
            '',
            $emptyLine,
            '<error>     ' . str_pad('Action required!', 75) . '</error>',
            $emptyLine,
            '<error>     ' . str_pad('The CakePHP plugin installer has been changed, please update your', 75) . '</error>',
            '<error>     ' . str_pad('application composer.json file to add the post-autoload-dump hook.', 75) . '</error>',
            '<error>     ' . str_pad('See the changes in https://github.com/cakephp/app/pull/216 for more info.', 75) . '</error>',
            $emptyLine,
            '',
            '',
        ];

        $this->io->write($messages);
    }

    /**
     * Called whenever composer (re)generates the autoloader
     *
     * Recreates CakePHP's plugin path map, based on composer information
     * and available app-plugins.
     *
     * @param Event $event
     * @return void
     */
    public static function postAutoloadDump(Event $event)
    {
        $composer = $event->getComposer();
        $config = $composer->getConfig();

        $vendorsDir = $config->get('vendor-dir');
        $root = dirname($vendorsDir);

        $packages = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
        $pluginsDir = $root . DIRECTORY_SEPARATOR . 'plugins';

        $plugins = static::determinePlugins($packages, $pluginsDir, $vendorsDir);

        $configFile = static::configFile($root);
        static::writeConfigFile($configFile, $plugins);
    }

    /**
     * Find all plugins available
     *
     * Add all composer packages of type cakephp-plugin, and all plugins located
     * in the plugins directory to a plugin-name indexed array of paths
     *
     * @param array $packages an array of \Composer\Package\PackageInterface objects
     * @param string $pluginsDir the path to the plugins dir
     * @param string $vendorsDir the path to the vendors dir
     * @return array plugin-name indexed paths to plugins
     */
    public static function determinePlugins($packages, $pluginsDir = 'plugins', $vendorsDir = 'vendors')
    {
        $plugins = [];

        foreach($packages as $package) {
            if ($package->getType() !== 'cakephp-plugin') {
                continue;
            }

            $ns = static::primaryNamespace($package);
            $path = $vendorsDir . DIRECTORY_SEPARATOR . $package->getPrettyName();
            $plugins[$ns] = $path;
        }

        if (is_dir($pluginsDir)) {
            $dir = new \DirectoryIterator($pluginsDir);
            foreach($dir as $info) {
                if (!$info->isDir() || $info->isDot()) {
                    continue;
                }

                $name = $info->getFilename();
                $plugins[$name] = $pluginsDir . DIRECTORY_SEPARATOR . $name;
            }
        }

        ksort($plugins);
        return $plugins;
    }

    /**
     * Rewrite the config file with a complete list of plugins
     *
     * @param string $configFile
     * @param array $plugins
     * @return void
     */
    public static function writeConfigFile($configFile, $plugins)
    {
        $root = dirname(dirname($configFile));

        $data = [];
        foreach ($plugins as $name => $pluginPath) {
            $pluginPath = str_replace(
                DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR,
                $pluginPath
            );

            // Normalize to *nix paths.
            $pluginPath = str_replace('\\', '/', $pluginPath);
            $pluginPath .= '/';

            $data[] = sprintf("        '%s' => '%s'", $name, $pluginPath);
        }

        $data = implode(",\n", $data);

        $contents = <<<PHP
<?php
\$baseDir = dirname(dirname(__FILE__));
return [
    'plugins' => [
$data
    ]
];

PHP;

        $root = str_replace(
            DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            $root
        );

        // Normalize to *nix paths.
        $root = str_replace('\\', '/', $root);
        $contents = str_replace('\'' . $root, '$baseDir . \'', $contents);
        file_put_contents($configFile, $contents);
    }

    /**
     * Path to the plugin config file
     *
     * @return string absolute file path
     */
    protected static function configFile($root)
    {
        return $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'plugins.php';
    }

    /**
     * Get the primary namespace for a plugin package.
     *
     * @param \Composer\Package\PackageInterface $package
     * @return string The package's primary namespace.
     * @throws \RuntimeException When the package's primary namespace cannot be determined.
     */
    public static function primaryNamespace($package)
    {
        $primaryNs = null;
        $autoLoad = $package->getAutoload();
        foreach ($autoLoad as $type => $pathMap) {
            if ($type !== 'psr-4') {
                continue;
            }
            $count = count($pathMap);

            if ($count === 1) {
                $primaryNs = key($pathMap);
                break;
            }

            $matches = preg_grep('#^(\./)?src/?$#', $pathMap);
            if ($matches) {
                $primaryNs = key($matches);
                break;
            }

            foreach (['', '.'] as $path) {
                $key = array_search($path, $pathMap, true);
                if ($key !== false) {
                    $primaryNs = $key;
                }
            }
            break;
        }

        if (!$primaryNs) {
            throw new RuntimeException(
                sprintf(
                    "Unable to get primary namespace for package %s.
                    Ensure you have added proper 'autoload' section to your plugin's config as stated in README on https://github.com/cakephp/plugin-installer",
                    $package->getName()
                )
            );
        }
        return trim($primaryNs, '\\');
    }

    /**
     * Decides if the installer supports the given type.
     *
     * This installer only supports package of type 'cakephp-plugin'.
     *
     * @return bool
     */
    public function supports($packageType)
    {
        return 'cakephp-plugin' === $packageType;
    }

    /**
     * Installs specific plugin.
     *
     * After the plugin is installed, app's `plugins.php` config file is updated with
     * plugin namespace to path mapping.
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo Repository in which to check.
     * @param \Composer\Package\PackageInterface $package Package instance.
     * @deprecated superceeded by the post-autoload-dump hook
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);
        $path = $this->getInstallPath($package);
        $ns = static::primaryNamespace($package);
        $this->updateConfig($ns, $path);
    }

    /**
     * Updates specific plugin.
     *
     * After the plugin is installed, app's `plugins.php` config file is updated with
     * plugin namespace to path mapping.
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo Repository in which to check.
     * @param \Composer\Package\PackageInterface $initial Already installed package version.
     * @param \Composer\Package\PackageInterface $target Updated version.
     * @deprecated superceeded by the post-autoload-dump hook
     *
     * @throws \InvalidArgumentException if $initial package is not installed
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);

        $ns = static::primaryNamespace($initial);
        $this->updateConfig($ns, null);

        $path = $this->getInstallPath($target);
        $ns = static::primaryNamespace($target);
        $this->updateConfig($ns, $path);
    }

    /**
     * Uninstalls specific package.
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo Repository in which to check.
     * @param \Composer\Package\PackageInterface $package Package instance.
     * @deprecated superceeded by the post-autoload-dump hook
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);
        $path = $this->getInstallPath($package);
        $ns = static::primaryNamespace($package);
        $this->updateConfig($ns, null);
    }

    /**
     * Update the plugin path for a given package.
     *
     * @param string $name The plugin name being installed.
     * @param string $path The path, the plugin is being installed into.
     */
    public function updateConfig($name, $path)
    {
        $name = str_replace('\\', '/', $name);
        $configFile = static::configFile(dirname($this->vendorDir));
        $this->ensureConfigFile($configFile);

        $return = include $configFile;
        if (is_array($return) && empty($config)) {
            $config = $return;
        }
        if (!isset($config)) {
            $this->io->write(
                'ERROR - Your `vendor/plugins.php` did not define a $config variable. ' .
                'Plugin path configuration not updated.'
            );
            return;
        }
        if (!isset($config['plugins'])) {
            $config['plugins'] = [];
        }
        if ($path == null) {
            unset($config['plugins'][$name]);
        } else {
            $path = str_replace(
                DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR,
                $path
            );

            // Normalize to *nix paths.
            $path = str_replace('\\', '/', $path);
            $path .= '/';

            $config['plugins'][$name] = $path;
        }
        $this->writeConfig($configFile, $config);
    }

    /**
     * Ensure that the vendor/plugins.php file exists.
     *
     * @param string $path the config file path.
     * @return void
     */
    protected function ensureConfigFile($path)
    {
        if (file_exists($path)) {
            if ($this->io->isVerbose()) {
                $this->io->write('vendor/plugins.php exists.');
            }
            return;
        }
        $contents = <<<'PHP'
<?php
$baseDir = dirname(dirname(__FILE__));
return [
    'plugins' => []
];
PHP;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path));
        }
        file_put_contents($path, $contents);

        if ($this->io->isVerbose()) {
            $this->io->write('Created vendor/plugins.php');
        }
    }

    /**
     * Dump the generate configuration out to a file.
     *
     * @param string $path The path to write.
     * @param array $config The config data to write.
     * @return void
     */
    protected function writeConfig($path, $config)
    {
        $root = dirname($this->vendorDir);
        $data = '';
        foreach ($config['plugins'] as $name => $pluginPath) {
            $data .= sprintf("        '%s' => '%s',\n", $name, $pluginPath);
        }
        $contents = <<<PHP
<?php
\$baseDir = dirname(dirname(__FILE__));
return [
    'plugins' => [
$data
    ]
];

PHP;
        $contents = str_replace('\'' . $root, '$baseDir . \'', $contents);
        file_put_contents($path, $contents);
    }
}
