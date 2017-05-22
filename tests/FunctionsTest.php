<?php
/**
 *
 */

namespace JPry\VVVBase;


use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{

    public function testFlags()
    {
        $flags = get_flags();
        $expected = array(
            'site:',
            'site_escaped:',
            'vm_dir:',
            'vvv_path_to_site:',
            'vvv_config:',
        );
        $this->assertEquals($expected, $flags);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Missing flags from command line: site, site_escaped, vm_dir, vvv_path_to_site, vvv_config
     */
    public function testValidateFlagsEmpty()
    {
        validate_flags(array());
    }
}
