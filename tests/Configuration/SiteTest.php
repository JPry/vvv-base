<?php
/**
 *
 */

namespace JPry\VVVBase\Configuration;


use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

/**
 * @group   config
 *
 * @package JPry\VVVBase\Configuration
 */
class SiteTest extends TestCase
{

    protected function getProcessed($config = array())
    {
        $processor = new Processor();
        return $processor->processConfiguration(new Site(), array($config));
    }


    protected function getCustomDefaults()
    {
        return array(
            'admin_user'             => 'admin',
            'password'               => 'password',
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

            // Deprecated options are still included for exact comparison.
            'wp-content'             => null,
            'prefix'                 => null,
            'dbprefix'               => null,
        );
    }

    /**
     * @dataProvider passedValuesProvider
     *
     * @author       Jeremy Pry
     *
     * @param $values
     * @param $expected
     */
    public function testPassedValues($values, $expected)
    {
        $this->assertEquals($expected, $this->getProcessed($values));
    }

    public function passedValuesProvider()
    {
        return array(
            // Test the defaults
            array(
                array(),
                array(
                    'hosts'  => array(),
                    'custom' => $this->getCustomDefaults(),
                ),
            ),

            // Test custom hosts
            array(
                array(
                    'hosts' => array('foo.local', 'bar.local'),
                ),
                array(
                    'hosts'  => array('foo.local', 'bar.local'),
                    'custom' => $this->getCustomDefaults(),
                ),
            ),

            // Test custom settings
            array(
                array(
                    'custom' => array(
                        'admin_user' => 'jpry',
                    ),
                ),
                array(
                    'hosts'  => array(),
                    'custom' => array_merge(
                        $this->getCustomDefaults(),
                        array(
                            'admin_user' => 'jpry',
                        )
                    ),
                ),
            ),

            array(
                array(
                    'custom' => array(
                        'skip_plugins' => array('jetpack'),
                    ),
                ),
                array(
                    'hosts'  => array(),
                    'custom' => array_merge(
                        $this->getCustomDefaults(),
                        array(
                            'skip_plugins' => array('jetpack'),
                        )
                    ),
                ),
            ),
        );
    }
}
