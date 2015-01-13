<?php
namespace Cake\Composer\Installer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use RuntimeException;

class PluginInstaller extends LibraryInstaller
{

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return 'cakephp-plugin' === $packageType;
    }

    /**
     * {@inheritDoc}
     */
    protected function installCode(PackageInterface $package)
    {
        parent::installCode($package);
        $path = $this->getInstallPath($package);
        $this->updateConfig($package->getName(), $path);
    }

    /**
     * {@inheritDoc}
     */
    protected function updateCode(PackageInterface $initial, PackageInterface $target)
    {
        parent::updateCode($initial, $target);
        $path = $this->getInstallPath($package);
        $this->updateConfig($package->getName(), $path);
    }

    /**
     * {@inheritDoc}
     */
    protected function removeCode(PackageInterface $package)
    {
        parent::removeCode($package);
        $path = $this->getInstallPath($package);
        $this->updateConfig($package->getName(), null);
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
                    "Unable to get plugin name for package %s. 
                    Ensure you have added proper 'autoload' section to your plugin's config as stated in README on https://github.com/cakephp/plugin-installer",
                    $package->getName()
                )
            );
        }
        return $primaryNs;
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

        include $configFile;
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
$config = [
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
        $contents = '<?php' . "\n" . '$config = ' . var_export($config, true) . ';';
        file_put_contents($path, $contents);
    }
}
