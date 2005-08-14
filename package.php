<?php

require_once 'PEAR/PackageFileManager.php';

$version = '0.2.0';
$notes = <<<EOT
- fixed bug #3031 (Cannot re-assign this)
- cosmetic fixes
- propagate PEAR errors to the user
EOT;

$description =<<<EOT
This package is a port of the core components of the RDF API for PHP (aka RAP):
http://www.wiwiss.fu-berlin.de/suhl/bizer/rdfapi/.
EOT;

$package =& new PEAR_PackageFileManager();

$result = $package->setOptions(array(
    'package'           => 'RDF',
    'summary'           => 'Port of the core RAP API',
    'description'       => $description,
    'version'           => $version,
    'state'             => 'alpha',
    'license'           => 'LGPL',
    'filelistgenerator' => 'cvs',
    'ignore'            => array('package.php', 'package.xml'),
    'notes'             => $notes,
    'changelogoldtonew' => false,
    'simpleoutput'      => true,
    'baseinstalldir'    => '/',
    'packagedirectory'  => './',
    'dir_roles'         => array('docs' => 'doc', 'examples' => 'doc', 'misc' => 'data')
    ));

if (PEAR::isError($result)) {
    echo $result->getMessage();
    die();
}

$package->addMaintainer('davey', 'lead', 'Davey Shafik', 'davey@php.net');

$package->addDependency('php', '4.2.0', 'ge', 'php', false);
$package->addDependency('PEAR', '1.0b1', 'ge', 'pkg', false);
$package->addDependency('MDB', true, 'has', 'pkg', true);

if (isset($_GET['make']) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'make')) {
    $result = $package->writePackageFile();
} else {
    $result = $package->debugPackageFile();
}

if (PEAR::isError($result)) {
    echo $result->getMessage();
    die();
}
