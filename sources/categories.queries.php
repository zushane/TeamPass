<?php
/**
 * @file          categories.queries.php
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

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset==utf-8");
include 'main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to mysql server
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/MysqliDb/MysqliDb.php';
$db = new MysqliDb($server, $user, $pass, $database, $pre);

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        case "addNewCategory":
            // store key
            $id = $db->insert(
                'categories',
                array(
                    'parent_id' => 0,
                    'title' => $_POST['title'],
                    'level' => 0,
                    'order' => 1
                )
            );
            echo '[{"error" : "", "id" : "'.$id.'"}]';
            break;
        case "deleteCategory":
            $db->where("id", $_POST['id']);
            $db->delete("categories");
            $db->where("category_id", $_POST['id']);
            $db->delete("categories_folders");
            echo '[{"error" : ""}]';
            break;
        case "addNewField":
            // store key
            if (!empty($_POST['title']) && !empty($_POST['id'])) {
                $id = $db->insert(
                    'categories',
                    array(
                        'parent_id' => $_POST['id'],
                        'title' => $_POST['title'],
                        'level' => 1,
                        'type' => 'text',
                        'order' => 1
                    )
                );
                echo '[{"error" : "", "id" : "'.$id.'"}]';
            }
            break;
        case "renameItem":
            // update key
            if (!empty($_POST['data']) && !empty($_POST['id'])) {
                $db->where("id", $_POST['id']);
                $db->update(
                    'categories',
                    array(
                        'title' => $_POST['data']
                       )
                );
                echo '[{"error" : "", "id" : "'.$_POST['id'].'"}]';
            }
            break;
        case "moveItem":
            // update key
            if (!empty($_POST['data']) && !empty($_POST['id'])) {
                $db->where("id", $_POST['id']);
                $db->update(
                    'categories',
                    array(
                        'parent_id' => $_POST['data'],
                        'order' => 99
                       )
                );
                echo '[{"error" : "", "id" : "'.$_POST['id'].'"}]';
            }
            break;
        case "saveOrder":
            // update order
            if (!empty($_POST['data'])) {
                foreach (explode(';', $_POST['data']) as $data) {
                    $elem = explode(':', $data);
                    $db->where("id", $elem[0]);
                    $db->update(
                        'categories',
                        array(
                            'order' => $elem[1]
                           )
                    );
                }
                echo '[{"error" : ""}]';
            }
            break;
        case "loadFieldsList":
            $categoriesSelect = "";
            $arrCategories = $arrFields = array();
            $rows = $db->rawQuery(
                "SELECT * FROM ".$pre."categories 
                WHERE level = ? 
                ORDER BY ".$pre."categories.order ASC",
                array(
                    "0"
                )
            );
            foreach ($rows as $reccord) {
                // get associated folders
                $foldersList = $foldersNumList = "";
                $rowsF = $db->rawQuery(
                    "SELECT t.title AS title, c.id_folder as id_folder
                    FROM ".$pre."categories_folders AS c
                    INNER JOIN ".$pre."nested_tree AS t ON (c.id_folder = t.id)
                    WHERE c.id_category = ?",
                    array(
                        $reccord['id']
                    )
                );
                foreach ($rowsF as $reccordF) {
                    if (empty($foldersList)) {
                        $foldersList = $reccordF['title'];
                        $foldersNumList = $reccordF['id_folder'];
                    } else {
                        $foldersList .= " | ".$reccordF['title'];
                        $foldersNumList .= ";".$reccordF['id_folder'];
                    }
                }
                
                // store
                array_push(
                    $arrCategories,
                    array(
                        '1',
                        $reccord['id'],
                        $reccord['title'],
                        $reccord['order'],
                        $foldersList,
                        $foldersNumList
                    )
                );
                $rows = $db->rawQuery(
                    "SELECT * FROM ".$pre."categories 
                    WHERE parent_id = ?
                    ORDER BY ".$pre."categories.order ASC",
                    array(
                        $reccord['id']
                    )
                );
                if (count($rows) > 0) {
                    foreach ($rows as $field) {
                        array_push(
                            $arrCategories,
                            array(
                                '2',
                                $field['id'],
                                $field['title'],
                                $field['order'],
                                '',
                                ''
                            )
                        );
                    }
                }
            }
            echo json_encode($arrCategories, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            break;
        case "categoryInFolders":
            // update order
            if (!empty($_POST['foldersIds'])) {
                // delete all existing inputs
                $db->where("id_category", $_POST['id']);
                $db->delete("categories_folders");
                // create new list
                $list = "";
                foreach (explode(';', $_POST['foldersIds']) as $folder) {
                    $db->insert(
                        'categories_folders',
                        array(
                            'id_category' => $_POST['id'],
                            'id_folder' => $folder
                           )
                    );
                    
                    // prepare a list
                    $row = $db->rawQuery(
                        "SELECT title 
                        FROM ".$pre."nested_tree 
                        WHERE id = ?",
                        array(
                            $_POST['id']
                        ),
                        true
                    );
                    if (empty($list)) {
                        $list = $row[0];
                    } else {
                        $list .= " | ".$row[0];
                    }
                }
                echo '[{"list" : "'.$list.'"}]';
            }
            break;
    }
}