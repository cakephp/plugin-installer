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

        $this->path = sys_get_temp_dir();
        if (!is_dir($this->path . '/config')) {
            mkdir($this->path . '/config');
        }

        $composer = new Composer();
        $config = $this->getMock('Composer\Config');
        $config->method('get')
            ->will($this->returnValue($this->path . '/vendor'));
        $composer->setConfig($config);

        $io = $this->getMock('Composer\IO\IOInterface');
        $rm = new RepositoryManager(
            $io,
            $config
        );
        $composer->setRepositoryManager($rm);

        $this->installer = new PluginInstaller($io, $composer);
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
     * Test getting primary namespace
     *
     * @return void
     */
    public function testprimaryNamespace()
    {
        $autoload = array(
            'psr-4' => array(
                'FOC\\Authenticate' => ''
            )
        );
        $this->package->setAutoload($autoload);

        $ns = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('FOC\Authenticate', $ns);

        $autoload = array(
            'psr-4' => array(
                'FOC\Acl\Test' => './tests',
                'FOC\Acl' => ''
            )
        );
        $this->package->setAutoload($autoload);
        $ns = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('FOC\Acl', $ns);

        $autoload = array(
            'psr-4' => array(
                'Foo\Bar' => 'foo',
                'Acme\Plugin' => './src'
            )
        );
        $this->package->setAutoload($autoload);
        $ns = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('Acme\Plugin', $ns);

        $autoload = array(
            'psr-4' => array(
                'Foo\Bar' => 'bar',
                'Foo' => ''
            )
        );
        $this->package->setAutoload($autoload);
        $ns = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('Foo', $ns);

        $autoload = array(
            'psr-4' => array(
                'Foo\Bar' => 'bar',
                'Foo' => '.'
            )
        );
        $this->package->setAutoload($autoload);
        $ns = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('Foo', $ns);

        $autoload = array(
            'psr-4' => array(
                'Acme\Foo\Bar' => 'bar',
                'Acme\Foo' => ''
            )
        );
        $this->package->setAutoload($autoload);
        $ns = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('Acme\Foo', $ns);

        $autoload = array(
            'psr-4' => array(
                'Acme\Foo\Bar' => '',
                'Acme\Foo' => 'src'
            )
        );
        $this->package->setAutoload($autoload);
        $ns = $this->installer->primaryNamespace($this->package);
        $this->assertEquals('Acme\Foo', $ns);
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
        $contents = file_get_contents($this->path . '/config/plugins.php');
        $this->assertContains('<?php', $contents);
        $this->assertContains("'DebugKit' => '/vendor/cakephp/debugkit/'", $contents);
        $this->assertContains("'Bake' => '/some/path'", $contents);
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
            '<?php return ["plugins" => ["Bake" => "/some/path"]];'
        );

        $this->installer->updateConfig('Bake', null);
        $contents = file_get_contents($this->path . '/config/plugins.php');
        $this->assertContains('<?php', $contents);
        $this->assertNotContains("Bake", $contents);
    }
}
