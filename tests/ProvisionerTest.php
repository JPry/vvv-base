<?php
/**
 *
 */

namespace JPry\VVVBase;

use JPry\VVVBase\Configuration\Site;
use JPry\VVVBase\Configuration\VBExtra;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder as PBuilder;

class ProvisionerTest extends TestCase
{
    /** @var  Processor */
    protected $configProcessor = null;

    /** @var \mysqli */
    protected $db_mock = null;

    /** @var  PBuilder */
    protected $processMock = null;

    /** @var  vfsStreamDirectory */
    protected $root = null;

    /** @var  array */
    protected $siteConfig = null;


    public function setUp()
    {
        // Wrap filesystem stream for cleaner testing.
        $this->root = vfsStream::setup();

        if (null === $this->configProcessor) {
            $this->configProcessor = new Processor();
        }
    }

    /**
     * Get a Provisioner instance.
     *
     * @author Jeremy Pry
     *
     * @param PBuilder $process
     * @param array    $config
     * @param Logger   $logger
     * @param array    $overrides
     *
     * @return Provisioner
     */
    protected function getProvisioner(
        $process = null,
        $config = array(),
        $logger = null,
        $overrides = array()
    ) {
        // Handle items that weren't passed.
        if (null === $process) {
            $prophesy = $this->prophesize(PBuilder::class);
            $process  = $prophesy->reveal();
        }
        if (null === $logger) {
            $logger = new Logger('provisionTest', array(new NullHandler()));
        }

        // Set up defaults
        $url       = $this->root->url();
        $name      = 'provision-test';
        $config    = $this->configProcessor->processConfiguration(new Site(), array($config));
        $overrides = ($this->configProcessor->processConfiguration(new VBExtra(), array($overrides)))['vvvbase'];

        return new Provisioner($process, $url, $name, $config, $logger, $overrides);
    }


    protected function getPublicMethod($method, $object)
    {
        $reflection = new \ReflectionClass($object);
        $method     = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method;
    }


    protected function getPublicProperty($property, $object)
    {
        $reflection = new \ReflectionClass($object);
        $property   = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property;
    }


    public function testSetupSite()
    {
        $provisioner = $this->getProvisioner();
        $site        = $this->getPublicProperty('site', $provisioner);
        $value       = $site->getValue($provisioner);

        $defaults = array(
            'admin_user'             => 'admin',
            'admin_password'         => 'password',
            'admin_email'            => 'admin@localhost.local',
            'title'                  => 'My Awesome VVV Site',
            'db_prefix'              => 'wp_',
            'multisite'              => false,
            'xipio'                  => true,
            'version'                => 'latest',
            'locale'                 => 'en_US',
            'plugins'                => array(),
            'themes'                 => array(),
            'delete_default_plugins' => false,
            'delete_default_themes'  => false,
            'wp_content'             => null,
            'wp'                     => true,
            'download_wp'            => true,
            'htdocs'                 => null,
            'skip_plugins'           => array(),
        );

        // Test the defaults from the site config
        foreach ($defaults as $key => $default) {
            $this->assertArrayHasKey($key, $value);
            $this->assertEquals($default, $value[$key]);
        }

        // Test the added defaults
        $this->assertEquals('provision-test.local', $value['main_host']);
        $this->assertEquals(array('provision-test.local'), $value['hosts']);

        // Test other set properties
        $baseDir = $this->getPublicProperty('base_dir', $provisioner);
        $this->assertEquals("{$this->root->url()}/htdocs", $baseDir->getValue($provisioner));

        $wpContent = $this->getPublicProperty('wp_content', $provisioner);
        $this->assertEquals("{$this->root->url()}/htdocs/wp-content", $wpContent->getValue($provisioner));
    }


    public function testCreateLogs()
    {
        $provisioner = $this->getProvisioner();
        $this->assertFalse($this->root->hasChild('log'));
        $vm_dir = $this->getPublicProperty('vm_dir', $provisioner);
        $vm_dir->setValue($provisioner, $this->root->url());

        $createLogs = $this->getPublicMethod('createLogs', $provisioner);
        $createLogs->invoke($provisioner);
        $this->assertTrue($this->root->hasChild('log'));
        $this->assertTrue($this->root->hasChild('log/error.log'));
        $this->assertTrue($this->root->hasChild('log/access.log'));
    }


    public function testCreateBaseDir()
    {
        $provisioner = $this->getProvisioner();
        $this->assertFalse($this->root->hasChild('htdocs'));
        $base_dir = $this->getPublicProperty('base_dir', $provisioner);
        $base_dir->setValue($provisioner, $this->root->url() . '/htdocs');

        $createBaseDir = $this->getPublicMethod('createBaseDir', $provisioner);
        $createBaseDir->invoke($provisioner);
        $this->assertTrue($this->root->hasChild('htdocs'));
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Invalid installer type: provisiontest
     */
    public function testInstallHelperException()
    {
        $provisioner = $this->getProvisioner();
        $helper      = $this->getPublicMethod('installHelper', $provisioner);
        $helper->invoke($provisioner, 'provisiontest', array());
    }


    public function testSkippedPlugins()
    {
        $config    = array(
            'custom' => array(
                'skip_plugins' => array('jetpack'),
            ),
        );
        $overrides = array(
            'vvvbase' => array(
                'plugins' => array(
                    array(
                        'plugin'   => 'jetpack',
                        'activate' => true,
                    ),
                ),
            ),
        );

        $loggerProphesy = $this->prophesize(Logger::class);
        $logger         = $loggerProphesy->reveal();
        $provisioner    = $this->getProvisioner(null, $config, $logger, $overrides);

        // Run the plugin install.
        $installPlugins = $this->getPublicMethod('installPlugins', $provisioner);
        $installPlugins->invoke($provisioner);

        // Assert what was supposed to have happened.
        $loggerProphesy->info('Installing plugins...')->shouldHaveBeenCalled();
        $loggerProphesy->info('Found jetpack in skip list, skipping...')->shouldHaveBeenCalled();
    }


    public function testGetCmd()
    {
        $provisioner = $this->getProvisioner(new PBuilder());
        $getCmd      = $this->getPublicMethod('getCmd', $provisioner);

        // Ensure proper handling of different values
        /** @var Process $result */
        $result = $getCmd->invoke(
            $provisioner,
            array('foo'),
            array(
                'bar' => false,
                'baz' => 'some value',
                'boo' => null,
            )
        );
        $this->assertEquals("'foo' '--baz=some value' '--boo'", $result->getCommandLine());
    }
}
