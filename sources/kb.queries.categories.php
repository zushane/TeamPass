<?php
/**
 * @file          kb.queries.categories.php
 * @author        Nils Laumaillé
 * @version       2.2.0
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
header("Content-type: text/x-json; charset=".$k['charset']);

//Connect to DB
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/MysqliDb/MysqliDb.php';
$db = new MysqliDb($server, $user, $pass, $database, $pre);

$queryWhere = $sOutput = "";
$queryParams = array();

//manage filtering
if (!empty($_GET['term'])) {
    $queryWhere = " WHERE category LIKE ?";
    $queryParams = array("%".$_GET['term']."%");
}

$rows = $db->rawQuery(
    "SELECT id, category FROM ".$pre."kb_categories".
    $queryWhere.
    "ORDER BY category ASC",
    $queryParams
);
if (count($rows)>0) {
    foreach ($rows as $reccord) {
        if (empty($sOutput)) {
            $sOutput = '"'.$reccord['category'].'"';
        } else {
            $sOutput .= ', "'.$reccord['category'].'"';
        }
    }

    //Finish the line
    echo '[ '.$sOutput.' ]';
}
