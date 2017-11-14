<?php
/**
 *
 */

namespace JPry\VVVBase;

use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{

    protected function incrementAssertionCount()
    {
        $this->addToAssertionCount(1);
    }

    public function testFlags()
    {
        $expected = [
            'site:',
            'site_escaped:',
            'vm_dir:',
            'vvv_path_to_site:',
            'vvv_config:',
        ];
        $this->assertEquals($expected, get_flags());
    }

    /**
     * @expectedException        \Exception
     * @expectedExceptionMessage Missing flags from command line: site, site_escaped, vm_dir, vvv_path_to_site, vvv_config
     * @expectedExceptionCode    1
     */
    public function testValidateFlagsEmpty()
    {
        validate_flags([]);
    }


    public function testValidateFlags()
    {
        $flags = array_flip(get_options());
        validate_flags($flags);
        $this->incrementAssertionCount();
    }

    /**
     * @expectedException        \Exception
     * @expectedExceptionMessage Cannot find site in config: testValidateSiteMissing
     * @expectedExceptionCode    2
     */
    public function testValidateSiteMissing()
    {
        validate_site([], __FUNCTION__);
    }


    public function testValidateSite()
    {
        $config = [
            'sites' => [
                __FUNCTION__ => [],
            ],
        ];
        validate_site($config, __FUNCTION__);
        $this->incrementAssertionCount();
    }
}
