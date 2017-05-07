<?php
/**
 *
 */

namespace JPry\VVVBase;

/**
 * Get an element from an array.
 *
 * @author Jeremy Pry
 *
 * @param string $key     The array key to search for.
 * @param array  $array   The array to search.
 * @param mixed  $default The default value if the element is not in the array.
 *
 * @return mixed The value from the array, or $default if it's not found.
 */
function el($key, $array, $default = null)
{
    if (array_key_exists($key, $array)) {
        return $array[$key];
    }

    return $default;
}

/**
 * Get the options passed via CLI.
 *
 * @return array
 */
function get_cli_options()
{
    return getopt('', get_flags());
}

/**
 * Get the array of options we recognize.
 *
 * @return array
 */
function get_options()
{
    return array(
        'site',
        'site_escaped',
        'vm_dir',
        'vvv_path_to_site',
        'vvv_config',
    );
}

/**
 * Convert the array of options into flags for CLI.
 *
 * @return array
 */
function get_flags()
{
    $flags = get_options();
    foreach ($flags as &$flag) {
        $flag .= ':';
    }

    return $flags;
}

/**
 * Validate that we were passed all of our flags that we need.
 *
 * @param array $options Parsed CLI options.
 *
 * @throws \Exception When some of the flags are missing.
 */
function validate_flags($options)
{
    $missing_flags = array_diff_key(array_flip(get_options()), $options);
    if (!empty($missing_flags)) {
        throw new \Exception('Missing flags from command line: ' . join(', ', $missing_flags), 1);
    }
}

/**
 * Validate that the site we need is found in the config array.
 *
 * @param array  $config The array of config data.
 * @param string $site   The site to validate.
 *
 * @throws \Exception When the site is not found in the config array.
 */
function validate_site($config, $site)
{
    if (!isset($config['sites'][$site])) {
        throw new \Exception("Cannot find site in config: {$site}", 2);
    }
}
