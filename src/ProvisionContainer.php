<?php

namespace JPry\VVVBase;

/**
 * Provisioner Container.
 *
 * @package JPry\VVVBase
 */
class ProvisionContainer implements ProvisionerInterface
{
    /**
     * Array of provisioner objects.
     *
     * @var ProvisionerInterface[]
     */
    protected $provisioners = [];

    /**
     * Provision each of our objects.
     *
     * @author Jeremy Pry
     */
    public function provision()
    {
        foreach ($this->provisioners as $provisioner) {
            $provisioner->provision();
        }
    }

    /**
     * Add a provisioner object.
     *
     * @author Jeremy Pry
     *
     * @param ProvisionerInterface $provisioner
     */
    public function addProvisioner(ProvisionerInterface $provisioner)
    {
        $this->provisioners[] = $provisioner;
    }
}
