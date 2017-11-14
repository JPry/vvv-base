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

    protected function getProcessed($config = [])
    {
        $processor = new Processor();
        return $processor->processConfiguration(new Site(), [$config]);
    }


    protected function getCustomDefaults()
    {
        return [
            'admin_user'             => 'admin',
            'admin_password'         => 'password',
            'admin_email'            => 'admin@localhost.local',
            'title'                  => 'My Awesome VVV Site',
            'db_prefix'              => 'wp_',
            'multisite'              => false,
            'xipio'                  => true,
            'version'                => 'latest',
            'locale'                 => 'en_US',
            'plugins'                => [],
            'themes'                 => [],
            'delete_default_plugins' => false,
            'delete_default_themes'  => false,
            'wp_content'             => null,
            'wp'                     => true,
            'download_wp'            => true,
            'htdocs'                 => null,
            'skip_plugins'           => [],

            // Deprecated options are still included for exact comparison.
            'wp-content'             => null,
            'prefix'                 => null,
            'dbprefix'               => null,
        ];
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
        return [
            // Test the defaults
            [
                [],
                [
                    'hosts'  => [],
                    'custom' => $this->getCustomDefaults(),
                ],
            ],

            // Test custom hosts
            [
                [
                    'hosts' => ['foo.local', 'bar.local'],
                ],
                [
                    'hosts'  => ['foo.local', 'bar.local'],
                    'custom' => $this->getCustomDefaults(),
                ],
            ],

            // Test custom settings
            [
                [
                    'custom' => [
                        'admin_user' => 'jpry',
                    ],
                ],
                [
                    'hosts'  => [],
                    'custom' => array_merge(
                        $this->getCustomDefaults(),
                        [
                            'admin_user' => 'jpry',
                        ]
                    ),
                ],
            ],

            [
                [
                    'custom' => [
                        'skip_plugins' => ['jetpack'],
                    ],
                ],
                [
                    'hosts'  => [],
                    'custom' => array_merge(
                        $this->getCustomDefaults(),
                        [
                            'skip_plugins' => ['jetpack'],
                        ]
                    ),
                ],
            ],
        ];
    }
}
