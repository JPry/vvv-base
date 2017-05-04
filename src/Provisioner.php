<?php
/**
 *
 */

namespace JPry\VVVBase;


class Provisioner
{
    protected $site_name;
    protected $config;
    protected $options;

    public function __construct($options)
    {
        $this->options = $options;
        $this->parseOptions();
    }

    public function provision()
    {

    }


    protected function parseOptions()
    {

    }
}
