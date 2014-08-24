<?php
namespace Cake\Test\TestCase\Composer\Installer;

use Cake\Composer\Installer\PluginInstaller;
use Composer\Composer;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\RepositoryManager;

class PluginInstallerTest extends \PHPUnit_Framework_TestCase {

	public $composer;

	public $io;

/**
 * setUp
 *
 * @return void
 */
	public function setUp() {
		$this->package = new Package('CamelCased', '1.0', '1.0');
		$this->io = $this->getMock('Composer\IO\PackageInterface');
		$this->composer = new Composer();
	}

/**
 * Test if installer-name was set
 *
 */
	public function testGetInstallPath() {
		$autoload = array(
			'psr-4' => array(
				'FOC\\Authenticate' => ''
			)
		);
		$this->package->setAutoload($autoload);
		$this->package->setType('cakephp-plugin');
		$rm = new RepositoryManager(
			$this->getMock('Composer\IO\IOInterface'),
			$this->getMock('Composer\Config')
		);
		$this->composer->setRepositoryManager($rm);
		$installer = new PluginInstaller($this->package, $this->composer);

		$this->setCakephpVersion($rm, '3.0.0');
		$path = $installer->getInstallPath($this->package, 'cakephp');
		$this->assertEquals('FOC/Authenticate', $path);

		$autoload = array(
			'psr-4' => array(
				'FOC\Acl\Test' => './tests',
				'FOC\Acl' => ''
			)
		);
		$this->package->setAutoload($autoload);
		$this->package->setExtra(array());
		$path = $installer->getInstallPath($this->package, 'cakephp');
		$this->assertEquals('FOC/Acl', $path);

		$autoload = array(
			'psr-4' => array(
				'Foo\Bar' => 'foo',
				'Acme\Plugin\Test' => 'tests',
				'Acme\Plugin' => './src'
			)
		);
		$this->package->setAutoload($autoload);
		$this->package->setExtra(array());
		$path = $installer->getInstallPath($this->package, 'cakephp');
		$this->assertEquals('Acme/Plugin', $path);

		$autoload = array(
			'psr-4' => array(
				'Foo\Bar' => 'bar',
				'Foo' => ''
			)
		);
		$this->package->setAutoload($autoload);
		$this->package->setExtra(array());
		$path = $installer->getInstallPath($this->package, 'cakephp');
		$this->assertEquals('Foo', $path);

		$autoload = array(
			'psr-4' => array(
				'Acme\Foo\Bar' => 'bar',
				'Acme\Foo' => ''
			)
		);
		$this->package->setAutoload($autoload);
		$this->package->setExtra(array());
		$path = $installer->getInstallPath($this->package, 'cakephp');
		$this->assertEquals('Acme/Foo', $path);

		$autoload = array(
			'psr-4' => array(
				'Acme\Foo\Bar' => '',
				'Acme\Foo' => 'src'
			)
		);
		$this->package->setAutoload($autoload);
		$this->package->setExtra(array());
		$path = $installer->getInstallPath($this->package, 'cakephp');
		$this->assertEquals('Acme/Foo', $path);
	}

}
