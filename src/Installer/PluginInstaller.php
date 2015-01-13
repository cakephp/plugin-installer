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
        $this->setConfig($package->getName(), $path);
    }

    /**
     * {@inheritDoc}
     */
    protected function updateCode(PackageInterface $initial, PackageInterface $target)
    {
        parent::updateCode($initial, $target);
        $path = $this->getInstallPath($package);
        $this->setConfig($package->getName(), $path);
    }

    /**
     * {@inheritDoc}
     */
    protected function removeCode(PackageInterface $package)
    {
        parent::removeCode($package);
        $path = $this->getInstallPath($package);
        $this->setConfig($package->getName(), null);
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
     * Set the plugin path for a given package.
     *
     * @param string $name The plugin name being installed.
     * @param string $path The path, the plugin is being installed into.
     */
    protected function setConfig($name, $path)
    {
    }
}
