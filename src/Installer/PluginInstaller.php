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
     *
     * @throws \RuntimeException
     */
    public function getPackageBasePath(PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (!empty($extra['installer-name'])) {
            return 'plugins/' . $extra['installer-name'];
        }

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
                    "Unable to get plugin name for package %s. 
                    Ensure you have added proper 'autoload' section to your plugin's config as stated in README on https://github.com/cakephp/plugin-installer",
                    $package->getName()
                )
            );
        }

        return 'plugins/' . trim(str_replace('\\', '/', $primaryNS), '/');
    }
}
