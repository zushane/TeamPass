<?php
/**
 * @file          find.queries.php
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

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");

//Connect to DB
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/MysqliDb/MysqliDb.php';
$db = new MysqliDb($server, $user, $pass, $database, $pre);

//Columns name
$aColumns = array('id', 'label', 'description', 'tags', 'id_tree', 'folder', 'login');

//init SQL variables
$sOrder = $sLimit = "";
$sWhere = "id_tree IN (".implode(', ', $_SESSION['groupes_visibles']).")";    //limit search to the visible folders
$queryAttr = array();
//array_push($queryAttr, implode(', ', $_SESSION['groupes_visibles']));

//Get current user "personal folder" ID
$row = $db->rawQuery(
    "SELECT id FROM ".$pre."nested_tree 
    WHERE title = ?",
    array(
        $_SESSION['user_id']
    ),
    true
);

//get list of personal folders
$arrayPf = array();
$listPf = "";
$rows = $db->rawQuery(
    "SELECT id FROM ".$pre."nested_tree 
    WHERE personal_folder = ? AND NOT parent_id = ? AND NOT title = ?",
    array(
        "1",
        $row['id'],
        $_SESSION['user_id']
    )
);
foreach ($rows as $reccord) {
    if (!in_array($reccord['id'], $arrayPf)) {
        //build an array of personal folders ids
        array_push($arrayPf, $reccord['id']);
        //build also a string with those ids
        if (empty($listPf)) {
            $listPf = $reccord['id'];
        } else {
            $listPf .= ', '.$reccord['id'];
        }
    }
}

/* BUILD QUERY */
//Paging
$sLimit = "";
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $sLimit = "LIMIT ". intval($_GET['iDisplayStart']) .", ". intval($_GET['iDisplayLength']);
}

//Ordering
if (isset($_GET['iSortCol_0'])) {
    $sOrder = "ORDER BY  ";
    for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
        if (
            $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" &&
            preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
        ) {
            $sOrder .= $aColumns[ intval($_GET['iSortCol_'.$i]) ]." ".$_GET['sSortDir_'.$i] .", ";
        }
    }

    $sOrder = substr_replace($sOrder, "", -2);
    if ($sOrder == "ORDER BY") {
            $sOrder = "";
    }
}

/*
 * Filtering
 * NOTE this does not match the built-in DataTables filtering which does it
 * word by word on any field. It's possible to do here, but concerned about efficiency
 * on very large tables, and MySQL's regex functionality is very limited
 */
if ($_GET['sSearch'] != "") {
    $sWhere .= " AND (";
    for ($i=0; $i<count($aColumns); $i++) {
        $sWhere .= $aColumns[$i]." LIKE ? OR ";
        array_push($queryAttr, "%".mysql_real_escape_string($_GET['sSearch'])."%");
    }
    $sWhere = substr_replace($sWhere, "", -3).") ";
}

// Do NOT show the items in PERSONAL FOLDERS
if (!empty($listPf)) {
    if (!empty($sWhere)) {
        $sWhere .= " AND ";
    }
    $sWhere = "WHERE ".$sWhere."id_tree NOT IN (".$listPf.") ";
    //array_push($queryAttr, $listPf);
} else {
    $sWhere = "WHERE ".$sWhere;
}

/* Total data set length */
$TotalObjects = $db->rawQuery(
    "SELECT COUNT(id)
    FROM   ".$pre."cache",
    null,
    true
);

$TotalObjectsFound = $db->rawQuery(
    "SELECT COUNT(id)
    FROM ".$pre."cache
    $sWhere",
    $queryAttr,
    true
);
//print_r($queryAttr);
/*
 * Output
 */
 
$rows = $db->query(
    "SELECT *
    FROM ".$pre."cache
    $sWhere
    $sOrder
    $sLimit"
);
//print $sWhere."\n";
$sOutput = '{';
$sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
$sOutput .= '"iTotalRecords": '.$TotalObjects['COUNT(id)'].', ';

// get number of objects returned by query
$TotalObjectsFound = $db->rawQuery(
    "SELECT COUNT(id)
    FROM ".$pre."cache
    $sWhere",
    null,
    true
);
if (empty($TotalObjectsFound['COUNT(id)'])) {
    $sOutput .= '"iTotalDisplayRecords": 0, ';
} else {
    $sOutput .= '"iTotalDisplayRecords": ' . $TotalObjectsFound['COUNT(id)'] . ', ';
}

$sOutput .= '"aaData": [ ';
$sOutputConst = "";


foreach ($rows as $reccord) {
    $getItemInList = true;
    $sOutputItem = "[";

    //col1
    $sOutputItem .= '"<img src=\"includes/images/key__arrow.png\" onClick=\"javascript:window.location.href = &#039;index.php?page=items&amp;group='.$reccord['id_tree'].'&amp;id='.$reccord['id'].'&#039;;\" style=\"cursor:pointer;\" />&nbsp;<img src=\"includes/images/eye.png\" onClick=\"javascript:see_item('.$reccord['id'].','.$reccord['perso'].');\" style=\"cursor:pointer;\" />&nbsp;<img src=\"includes/images/key_copy.png\" onClick=\"javascript:copy_item('.$reccord['id'].');\" style=\"cursor:pointer;\" />", ';

    //col2
    $sOutputItem .= '"'.htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES).'", ';

    //col3
    $sOutputItem .= '"'.str_replace("&amp;", "&", htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES)).'", ';

    //col4
    //get restriction from ROles
    $restrictedToRole = false;
    $qTmp = $db->rawQuery(
        "SELECT role_id FROM ".$pre."restriction_to_roles 
        WHERE item_id = ?",
        array(
            $reccord['id']
        )
    );
    foreach ($qTmp as $aTmp) {
        if ($aTmp['role_id'] != "") {
            if (!in_array($aTmp['role_id'], $_SESSION['user_roles'])) {
                $restrictedToRole = true;
            }
        }
    }
    
    /*
    $rTmp = mysql_query(
        
    ) or die(mysql_error());
    while ($aTmp = mysql_fetch_row($rTmp)) {
        if ($aTmp[0] != "") {
            if (!in_array($aTmp[0], $_SESSION['user_roles'])) {
                $restrictedToRole = true;
            }
        }
    }
    */

    //echo in_array($_SESSION['user_roles'], $a);
    if (
        ($reccord['perso']==1 && $reccord['author'] != $_SESSION['user_id'])
        ||
        (
            !empty($reccord['restricted_to'])
            && !in_array($_SESSION['user_id'], explode(';', $reccord['restricted_to']))
        )
        ||
        (
            $restrictedToRole == true
        )
    ) {
        $getItemInList = false;
    } else {
        $txt = str_replace(array('\n', '<br />', '\\'), array(' ', ' ', ''), strip_tags($reccord['description']));
        if (strlen($txt) > 50) {
            $sOutputItem .= '"'.substr(stripslashes(preg_replace('/<[^>]*>|[\t]/', '', $txt)), 0, 50).'", ';
        } else {
            $sOutputItem .= '"'.stripslashes(preg_replace('/<[^>]*>|[\t]/', '', $txt)).'", ';
        }
    }

    //col5 - TAGS
    $sOutputItem .= '"'.htmlspecialchars(stripslashes($reccord['tags']), ENT_QUOTES).'", ';

    //col6 - Prepare the Treegrid
    $sOutputItem .= '"'.htmlspecialchars(stripslashes($reccord['folder']), ENT_QUOTES).'"';

    //Finish the line
    $sOutputItem .= '], ';

    if ($getItemInList == true) {
        $sOutputConst .= $sOutputItem;
    }
}
if (!empty($sOutputConst)) {
    $sOutput .= substr_replace($sOutputConst, "", -2);
}
$sOutput .= '] }';

echo $sOutput;
