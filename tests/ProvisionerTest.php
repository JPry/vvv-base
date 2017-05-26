<?php
/**
 *
 */

namespace JPry\VVVBase;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ProcessBuilder;

class ProvisionerTest extends TestCase
{
    /**
     * @var Provisioner
     */
    protected $provisioner;

    /** @var \mysqli */
    protected $db_mock;


    public static function setUpBeforeClass()
    {

    }

    public function setUp()
    {
        if (null === $this->db_mock) {
            $this->db_mock = $this->getMockBuilder('\mysqli')
                                  ->disableOriginalConstructor()
                                  ->disableOriginalClone()
                                  ->getMock();
            $this->db_mock->expects($this->atMost(3))
                          ->method('query');
        }

        $this->provisioner = new Provisioner(
            new ProcessBuilder(),
            $this->db_mock,
            '/tmp/provision-test',
            'provision-test',
            array()
        );
    }


    protected function getPublicMethod($method)
    {
        $reflection = new \ReflectionClass($this->provisioner);
        $method     = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method;
    }


    protected function getPublicProperty($property)
    {
        $reflection = new \ReflectionClass($this->provisioner);
        $property   = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property;
    }


    public function testSiteSetup()
    {
        $site  = $this->getPublicProperty('site');
        $value = $site->getValue($this->provisioner);

        $this->assertArrayNotHasKey('admin_user', $value);
        $this->assertEquals('admin', $value['admin_user']);
    }
}
