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
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package) {
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
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package) {
        parent::uninstall($repo, $package);
        $path = $this->getInstallPath($package);
        $ns = $this->primaryNamespace($package);
        $this->updateConfig($ns, $path);
    }

    /**
     * Update the plugin path for a given package.
     *
     * @param string $name The plugin name.
     * @param string|null $path The path, the plugin is being installed into or
     *   Null to remove plugin from config.
     * @return void
     */
    public function updateConfig($name, $path)
    {
        $configPath = dirname($this->vendorDir) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        $configEngine = new PhpConfig($configPath);

        $config = $configEngine->read('plugins');
        if ($path === null) {
            unset($config[$name]);
        } else {
            $path = str_replace(
                DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR,
                $path . DIRECTORY_SEPARATOR
            );
            $config[$name] = $path;
        }
        $configEngine->dump($config);
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
        $primaryNS = null;
        $autoLoad = $package->getAutoload();
        foreach ($autoLoad as $type => $pathMap) {
            if ($type !== 'psr-4') {
                continue;
            }
            $count = count($pathMap);

            if ($count === 1) {
                $primaryNS = key($pathMap);
                break;
            }

            $matches = preg_grep('#^(\./)?src/?$#', $pathMap);
            if ($matches) {
                $primaryNS = key($matches);
                break;
            }

            foreach (['', '.'] as $path) {
                $key = array_search($path, $pathMap, true);
                if ($key !== false) {
                    $primaryNS = $key;
                }
            }
            break;
        }

        if (!$primaryNS) {
            throw new RuntimeException(
                sprintf(
                	"Unable to get primary namespace for package %s.
                	Ensure you have added proper 'autoload' section to your plugin's config as stated in README on https://github.com/cakephp/plugin-installer",
                	$package->getName()
                )
            );
        }

        return trim($primaryNS, '\\');
    }
}
