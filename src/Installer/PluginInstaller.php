<?php
namespace Cake\Composer\Installer;

use Cake\Core\Configure\Engine\PhpConfig;
use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use RuntimeException;

class PluginInstaller extends LibraryInstaller
{

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
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);
        $path = $this->getInstallPath($package);
        $ns = $this->primaryNamespace($package);
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
     *
     * @throws \InvalidArgumentException if $initial package is not installed
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);

        $ns = $this->primaryNamespace($initial);
        $this->updateConfig($ns, null);

        $path = $this->getInstallPath($target);
        $ns = $this->primaryNamespace($target);
        $this->updateConfig($ns, $path);
    }

    /**
     * Uninstalls specific package.
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo Repository in which to check.
     * @param \Composer\Package\PackageInterface $package Package instance.
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);
        $path = $this->getInstallPath($package);
        $ns = $this->primaryNamespace($package);
        $this->updateConfig($ns, null);
    }

    /**
     * Get the primary namespace for a plugin package.
     *
     * @param \Composer\Package\PackageInterface $package
     * @return string The package's primary namespace.
     * @throws \RuntimeException When the package's primary namespace cannot be determined.
     */
    public function primaryNamespace($package)
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
     * Update the plugin path for a given package.
     *
     * @param string $name The plugin name being installed.
     * @param string $path The path, the plugin is being installed into.
     */
    public function updateConfig($name, $path)
    {
        $root = dirname($this->vendorDir);
        $configFile = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'plugins.php';
        $this->ensureConfigFile($configFile);

        $return = include $configFile;
        if (is_array($return) && empty($config)) {
            $config = $return;
        }
        if (!isset($config)) {
            $this->io->write(
                'ERROR - Your `config/plugins.php` did not define a $config variable. ' .
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
                $path . DIRECTORY_SEPARATOR
            );
            $config['plugins'][$name] = $path;
        }
        $this->writeConfig($configFile, $config);
    }

    /**
     * Ensure that the config/plugins.php file exists.
     *
     * @param string $path the config file path.
     * @return void
     */
    protected function ensureConfigFile($path)
    {
        if (file_exists($path)) {
            if ($this->io->isVerbose()) {
                $this->io->write('config/plugins.php exists.');
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
            $this->io->write('Created config/plugins.php');
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
        $contents = '<?php' . "\n" . '$baseDir = dirname(dirname(__FILE__));' . "\n" . 'return ' . var_export($config, true) . ';';
        $contents = str_replace('\'' . $root, '$baseDir . \'', $contents);
        file_put_contents($path, $contents);
    }
}
