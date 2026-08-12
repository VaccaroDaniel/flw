<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'postgres';
$CFG->dbpass    = 'postgres';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
);

$CFG->wwwroot   = 'https://192.168.129.79';
#$CFG->wwwroot   = 'http://192.168.129.79';
$CFG->dataroot  = 'D:\\Dev\\MoodleWindowsInstaller-latest-501\\server/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

// PHPUnit test environment. Uses a separate table prefix and dataroot from the live site.
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = 'C:\\Users\\com\\Documents\\Estimation Speaking\\moodledata_phpunit';
$CFG->phpunit_directorypermissions = 0777;


// Behat acceptance-test environment. Uses a separate table prefix, dataroot, and wwwroot from the live site.
$CFG->behat_wwwroot = 'http://192.168.129.79';
$CFG->behat_prefix = 'bht_';
$CFG->behat_dataroot = 'C:\Users\com\Documents\Estimation Speaking\moodledata_behat';
$CFG->behat_faildump_path = 'C:\Users\com\Documents\Estimation Speaking\moodledata_behat_faildump';
require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
