<?php
namespace Cake\Test\TestCase\Composer\Installer;

use Cake\Composer\Installer\PluginInstaller;
use Composer\Composer;
use Composer\Config;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\RepositoryManager;

class PluginInstallerTest extends \PHPUnit_Framework_TestCase
{

    public $package;

    public $installer;

    /**
     * setUp
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->package = new Package('CamelCased', '1.0', '1.0');
        $this->package->setType('cakephp-plugin');

        $composer = new Composer();
        $path = sys_get_temp_dir();
        $config = $this->getMock('Composer\Config');
        $config->method('get')
            ->will($this->returnValue($path));
        $composer->setConfig($config);
        $this->path = dirname($path);

        $this->io = $this->getMock('Composer\IO\IOInterface');
        $rm = new RepositoryManager(
            $this->io,
            $config
        );
        $composer->setRepositoryManager($rm);

        $this->installer = new PluginInstaller($this->io, $composer);
    }

    /**
     * teardown
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        if (is_file($this->path . '/config/plugins.php')) {
            unlink($this->path . '/config/plugins.php');
        }
    }

    /**
     * Ensure that primary namespace detection works.
     *
     * @return void
     */
    public function testPrimaryNamespace()
    {
        $autoload = array(
            'psr-4' => array(
                'FOC\\Authenticate' => ''
            )
        );
        $this->package->setAutoload($autoload);

        $name = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('FOC\Authenticate', $name);

        $autoload = array(
            'psr-4' => array(
                'FOC\Acl\Test' => './tests',
                'FOC\Acl' => ''
            )
        );
        $this->package->setAutoload($autoload);
        $name = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('FOC\Acl', $name);

        $autoload = array(
            'psr-4' => array(
                'Foo\Bar' => 'foo',
                'Acme\Plugin' => './src'
            )
        );
        $this->package->setAutoload($autoload);
        $name = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('Acme\Plugin', $name);

        $autoload = array(
            'psr-4' => array(
                'Foo\Bar' => 'bar',
                'Foo\\' => ''
            )
        );
        $this->package->setAutoload($autoload);
        $name = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('Foo', $name);

        $autoload = array(
            'psr-4' => array(
                'Foo\Bar' => 'bar',
                'Foo' => '.'
            )
        );
        $this->package->setAutoload($autoload);
        $name = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('Foo', $name);

        $autoload = array(
            'psr-4' => array(
                'Acme\Foo\Bar' => 'bar',
                'Acme\Foo\\' => ''
            )
        );
        $this->package->setAutoload($autoload);
        $name = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('Acme\Foo', $name);

        $autoload = array(
            'psr-4' => array(
                'Acme\Foo\Bar' => '',
                'Acme\Foo' => 'src'
            )
        );
        $this->package->setAutoload($autoload);
        $name = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('Acme\Foo', $name);
    }

    public function testUpdateConfigNoConfigFile()
    {
        $this->installer->updateConfig('DebugKit', '/vendor/cakephp/DebugKit');
        $this->assertFileExists($this->path . '/config/plugins.php');
        $contents = file_get_contents($this->path . '/config/plugins.php');
        $this->assertContains('<?php', $contents);
        $this->assertContains("'plugins' =>", $contents);
        $this->assertContains("'DebugKit' => '/vendor/cakephp/DebugKit/'", $contents);
    }

    public function testUpdateConfigAddPathInvalidFile()
    {
        file_put_contents($this->path . '/config/plugins.php', '<?php $foo = "DERP";');

        $this->io->expects($this->once())
            ->method('write');
        $this->installer->updateConfig('DebugKit', '/vendor/cakephp/DebugKit');
    }

    public function testUpdateConfigAddPathFileExists()
    {
        file_put_contents($this->path . '/config/plugins.php', '<?php $config = ["Bake" => "/some/path"];');

        $this->installer->updateConfig('DebugKit', '/vendor/cakephp/DebugKit');
        $contents = file_get_contents($this->path . '/config/plugins.php');
        $this->assertContains('<?php', $contents);
        $this->assertContains("'plugins' =>", $contents);
        $this->assertContains("'DebugKit' => '/vendor/cakephp/DebugKit/'", $contents);
        $this->assertContains("'Bake' => '/some/path'", $contents);
    }

    public function testUpdateConfigRemovePath()
    {
        file_put_contents(
            $this->path . '/config/plugins.php',
            '<?php $config = ["plugins" => ["Bake" => "/some/path"]];'
        );

        $this->installer->updateConfig('Bake', '');
        $contents = file_get_contents($this->path . '/config/plugins.php');
        $this->assertContains('<?php', $contents);
        $this->assertContains("'plugins' =>", $contents);
        $this->assertNotContains("Bake", $contents);
    }
}
