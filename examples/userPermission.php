<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require(realpath(dirname(__FILE__)) . '/../vendor/autoload.php');
require(realpath(dirname(__FILE__)) . '/../config.inc.php');

use PierreGranger\ApidaeSso;

$configApidaeSso['debug'] = true;
$ApidaeSso = new ApidaeSso($configApidaeSso, $_SESSION['ApidaeSso']);

if (!$ApidaeSso->connected()) {
    header('Location:./sso.php');
    return;
}

$tests = array(
    5679881, // object qui existe
    56798819999, // object qui n'existe pas
    'test', // erreur d'appel
);

foreach ($tests as $id) {
    echo '<h2>' . $id . '</h2>';
    try {
        var_dump($ApidaeSso->getUserPermissionOnObject($id));
    } catch (Exception $e) {
        var_dump($e);
    }
}
