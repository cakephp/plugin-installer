<?php
namespace Cake\Test\TestCase\Composer\Installer;

use Cake\Test\Composer\Installer\PluginInstaller;
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

        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plugin-installer-test';
        if (!is_dir($this->path . '/config')) {
            mkdir($this->path . '/config');
        }

        $composer = new Composer();
        $config = $this->getMock('Composer\Config');
        $config->expects($this->any())
            ->method('get')
            ->will($this->returnValue($this->path . '/vendor'));
        $composer->setConfig($config);

        $this->io = $this->getMock('Composer\IO\IOInterface');
        $rm = new RepositoryManager(
            $this->io,
            $config
        );
        $composer->setRepositoryManager($rm);

        $this->installer = new PluginInstaller($this->io, $composer);
    }

    public function tearDown()
    {
        parent::tearDown();
        if (is_file($this->path . '/config/plugins.php')) {
            unlink($this->path . '/config/plugins.php');
        }
        rmdir($this->path . '/config');
    }

    /**
     * Sanity test
     *
     * The test double should return a path to a test file, where
     * the containing folder
     *
     * @return void
     */
    public function testConfigFile()
    {
        $path = PluginInstaller::configFile("");
        $this->assertFileExists(dirname($path));
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

        $ns = PluginInstaller::primaryNamespace($this->package);
        $this->assertEquals('FOC\Authenticate', $ns);

        $autoload = array(
            'psr-4' => array(
                'FOC\Acl\Test' => './tests',
                'FOC\Acl' => ''
            )
        );
        $this->package->setAutoload($autoload);
        $ns = PluginInstaller::primaryNamespace($this->package);
        $this->assertEquals('FOC\Acl', $ns);

        $autoload = array(
            'psr-4' => array(
                'Foo\Bar' => 'foo',
                'Acme\Plugin' => './src'
            )
        );
        $this->package->setAutoload($autoload);
        $ns = PluginInstaller::primaryNamespace($this->package);
        $this->assertEquals('Acme\Plugin', $ns);

        $autoload = array(
            'psr-4' => array(
                'Foo\Bar' => 'bar',
                'Foo\\' => ''
            )
        );
        $this->package->setAutoload($autoload);
        $ns = PluginInstaller::primaryNamespace($this->package);
        $this->assertEquals('Foo', $ns);

        $autoload = array(
            'psr-4' => array(
                'Foo\Bar' => 'bar',
                'Foo' => '.'
            )
        );
        $this->package->setAutoload($autoload);
        $ns = PluginInstaller::primaryNamespace($this->package);
        $this->assertEquals('Foo', $ns);

        $autoload = array(
            'psr-4' => array(
                'Acme\Foo\Bar' => 'bar',
                'Acme\Foo\\' => ''
            )
        );
        $this->package->setAutoload($autoload);
        $ns = PluginInstaller::primaryNamespace($this->package);
        $this->assertEquals('Acme\Foo', $ns);

        $autoload = array(
            'psr-4' => array(
                'Acme\Foo\Bar' => '',
                'Acme\Foo' => 'src'
            )
        );
        $this->package->setAutoload($autoload);
        $name = PluginInstaller::primaryNamespace($this->package);
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
        file_put_contents(
            $this->path . '/config/plugins.php',
            '<?php $config = ["plugins" => ["Bake" => "/some/path"]];'
        );

        $this->installer->updateConfig('DebugKit', '/vendor/cakephp/DebugKit');
        $contents = file_get_contents($this->path . '/config/plugins.php');
        $this->assertContains('<?php', $contents);
        $this->assertContains("'plugins' =>", $contents);
        $this->assertContains("'DebugKit' => '/vendor/cakephp/DebugKit/'", $contents);
        $this->assertContains("'Bake' => '/some/path'", $contents);
    }

    /**
     * testUpdateConfigAddRootPath
     *
     * @return void
     */
    public function testUpdateConfigAddRootPath() {
        file_put_contents($this->path . '/config/plugins.php', '<?php return ["plugins" => ["Bake" => "/some/path"]];');

        $this->installer->updateConfig('DebugKit', $this->path . '/vendor/cakephp/debugkit');
        $contents = file_get_contents($this->path . '/config/plugins.php');
        $this->assertContains('<?php', $contents);
        $this->assertContains('$baseDir = dirname(dirname(__FILE__));', $contents);
        $this->assertContains("'DebugKit' => \$baseDir . '/vendor/cakephp/debugkit/'", $contents);
        $this->assertContains("'Bake' => '/some/path'", $contents);
    }

    /**
     * testUpdateConfigAddPath
     *
     * @return void
     */
    public function testUpdateConfigAddPath()
    {
        file_put_contents($this->path . '/config/plugins.php', '<?php return ["plugins" => ["Bake" => "/some/path"]];');

        $this->installer->updateConfig('DebugKit', '/vendor/cakephp/debugkit');
        $this->installer->updateConfig('ADmad\JwtAuth', '/vendor/admad/cakephp-jwt-auth');

        $contents = file_get_contents($this->path . '/config/plugins.php');
        $this->assertContains('<?php', $contents);
        $this->assertContains("'DebugKit' => '/vendor/cakephp/debugkit/'", $contents);
        $this->assertContains("'Bake' => '/some/path'", $contents);
        $this->assertContains("'ADmad/JwtAuth' => '/vendor/admad/cakephp-jwt-auth/'", $contents);
    }

    /**
     * test adding windows paths.
     *
     * @return void
     */
    public function testUpdateConfigAddPathWindows()
    {
        file_put_contents($this->path . '/config/plugins.php', '<?php return ["plugins" => ["Bake" => "/some/path"]];');

        $this->installer->updateConfig('DebugKit', '\vendor\cakephp\debugkit');

        $contents = file_get_contents($this->path . '/config/plugins.php');
        $this->assertContains('<?php', $contents);
        $this->assertContains("'DebugKit' => '/vendor/cakephp/debugkit/'", $contents);
    }

    /**
     * testUpdateConfigRemovePath
     *
     * @return void
     */
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
