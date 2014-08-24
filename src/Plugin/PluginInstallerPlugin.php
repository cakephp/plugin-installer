<?php
namespace Cake\Composer\Plugin;

use Cake\Composer\Installer\PluginInstaller;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class PluginInstallerPlugin implements PluginInterface {

/**
 * {@inheritDoc}
 */
	public function activate(Composer $composer, IOInterface $io) {
		$installer = new PluginInstaller($io, $composer);
		$composer->getInstallationManager()->addInstaller($installer);
	}

}