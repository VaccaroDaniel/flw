<?php return array(
    'root' => array(
        'name' => 'moodle/moodle',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '3803e077b141b90ec561c600736fec1dba1a7be9',
        'type' => 'moodle-core',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'moodle/lms' => array(
            'dev_requirement' => false,
            'provided' => array(
                0 => '5.1',
            ),
        ),
        'moodle/moodle' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '3803e077b141b90ec561c600736fec1dba1a7be9',
            'type' => 'moodle-core',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
