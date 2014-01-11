<?php
/**
 * @file          folders.queries.php
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

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// CASE where title is changed
if (isset($_POST['newtitle'])) {
    $id = explode('_', $_POST['id']);
    //update DB
    $db->where("id", $id[1]);
    $db->update(
        'nested_tree',
        array(
            'title' => mysql_real_escape_string(stripslashes(($_POST['newtitle'])))
        )
    );
    //Show value
    echo ($_POST['newtitle']);

    // CASE where RENEWAL PERIOD is changed
} elseif (isset($_POST['renewal_period']) && !isset($_POST['type'])) {

    //Check if renewal period is an integer
    if (parseInt(intval($_POST['renewal_period']))) {
        $id = explode('_', $_POST['id']);
        //update DB
        $db->where("id", $id[1]);
        $db->update(
            'nested_tree',
            array(
                'renewal_period' => mysql_real_escape_string(stripslashes(($_POST['renewal_period'])))
           )
        );
        //Show value
        echo ($_POST['renewal_period']);
    } else {
        //Show ERROR
        echo ($txt['error_renawal_period_not_integer']);
    }

    // CASE where the parent is changed
} elseif (isset($_POST['newparent_id'])) {
    $id = explode('_', $_POST['id']);
    //Store in DB
    $db->where("id", $id[1]);
    $db->update(
        'nested_tree',
        array(
            'parent_id' => $_POST['newparent_id']
       )
    );
    //Get the title to display it
    $data = $db->rawQuery(
        "SELECT title 
        FROM ".$pre."nested_tree 
        WHERE id = ?",
        array(  
            $_POST['newparent_id']
        ),
        true
    );
    //show value
    echo ($data[0]);
    //rebuild the tree grid
    $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
    $tree->rebuild();

    // CASE where complexity is changed
} elseif (isset($_POST['changer_complexite'])) {
    $id = explode('_', $_POST['id']);

    //Check if group exists
    $tmp = $db->rawQuery(
        "SELECT COUNT(*) 
        FROM ".$pre."misc 
        WHERE type = ? AND intitule = ?",
        array(
            "complex",
            $id[1]
        ),
        true
    );
    if ($tmp[0] == 0) {
        //Insert into DB
        $db->insert(
            'misc',
            array(
                'type' => 'complex',
                'intitule' => $id[1],
                'valeur' => $_POST['changer_complexite']
           )
        );
    } else {
        //update DB
        $db->where("type", "complex");
        $db->where("intitule", $id[1]);
        $db->update(
            'misc',
            array(
                'valeur' => $_POST['changer_complexite']
            )
        );
    }

    //Get title to display it
    echo $pwComplexity[$_POST['changer_complexite']][1];

    //rebuild the tree grid
    $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
    $tree->rebuild();

    // Several other cases
} elseif (isset($_POST['type'])) {
    switch ($_POST['type']) {
        // CASE where DELETING a group
        case "delete_folder":
            $foldersDeleted = "";
            // this will delete all sub folders and items associated
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

            // Get through each subfolder
            $folders = $tree->getDescendants($_POST['id'], true);
            foreach ($folders as $folder) {
                if (($folder->parent_id > 0 || $folder->parent_id == 0) && $folder->title != $_SESSION['user_id'] ) {
                    //Store the deleted folder (recycled bin)
                    $db->insert(
                        'misc',
                        array(
                            'type' => 'folder_deleted',
                            'intitule' => "f".$_POST['id'],
                            'valeur' => $folder->id.', '.$folder->parent_id.', '.
                            $folder->title.', '.$folder->nleft.', '.$folder->nright.', '.
                            $folder->nlevel.', 0, 0, 0, 0'
                       )
                    );
                    //delete folder
                    $db->where("id", $folder->id);
                    $db->delete("nested_tree");

                    //delete items & logs
                    $items = $db->rawQuery(
                        "SELECT id 
                        FROM ".$pre."items 
                        WHERE id_tree = ?",
                        array(
                            $folder->id
                        )
                    );
                    foreach ($items as $item) {
                        $db->where("id", $item['id']);
                        $db->update(
                            "items",
                            array(
                                'inactif' => '1',
                            )
                        );
                        //log
                        $db->insert(
                            "log_items",
                            array(
                                'id_item' => $item['id'],
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_delete'
                            )
                        );
                    }

                    //Actualize the variable
                    $_SESSION['nb_folders'] --;
                }
            }

            //rebuild tree
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $tree->rebuild();

            //Update CACHE table
            updateCacheTable("delete_value", $_POST['id']);
            break;

        //CASE where ADDING a new group
        case "add_folder":
            $error = "";

            //decrypt and retreive data in JSON format
            $dataReceived = json_decode(
                Encryption\Crypt\aesctr::decrypt($_POST['data'], $_SESSION['key'], 256),
                true
            );

            //Prepare variables
            $title = htmlspecialchars_decode($dataReceived['title']);
            $complexity = htmlspecialchars_decode($dataReceived['complexity']);
            $parentId = htmlspecialchars_decode($dataReceived['parent_id']);
            $renewalPeriod = htmlspecialchars_decode($dataReceived['renewal_period']);

            //Check if title doesn't contains html codes
            if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $title, $out)) {
                $error = 'error_html_codes';
            }

            //Check if duplicate folders name are allowed
            $createNewFolder = true;
            if (isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 0) {
                $data = $db->rawQuery(
                    "SELECT COUNT(*) 
                    FROM ".$pre."nested_tree 
                    WHERE title = ?",
                    array(
                        addslashes($title)
                    ),
                    true
                );
                if ($data['COUNT(*)'] != 0) {
                    $error = 'error_group_exist';
                    $createNewFolder = false;
                }
            }

            if ($createNewFolder == true) {
                //check if parent folder is personal
                $data = $db->rawQuery(
                    "SELECT personal_folder 
                    FROM ".$pre."nested_tree 
                    WHERE id = ?",
                    array(
                        $parentId
                    ),
                    true
                );
                if ($data['personal_folder'] == 1) {
                    $isPersonal = 1;
                } else {
                    $isPersonal = 0;
                }

                //create folder
                $newId=$db->insert(
                    "nested_tree",
                    array(
                        'parent_id' => $parentId,
                        'title' => $title,
                        'personal_folder' => $isPersonal,
                        'renewal_period' => $renewalPeriod,
                        'bloquer_creation' => '0',
                        'bloquer_modification' => '0'
                   )
                );

                //Add complexity
                $db->insert(
                    "misc",
                    array(
                        'type' => 'complex',
                        'intitule' => $newId,
                        'valeur' => $complexity
                   )
                );

                $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
                $tree->rebuild();

                if ($isPersonal != 1){
                    //Get user's rights
                    @identifyUserRights(
                        $_SESSION['groupes_visibles'].';'.$newId,
                        $_SESSION['groupes_interdits'],
                        $_SESSION['is_admin'],
                        $_SESSION['fonction_id'],
                        true
                    );

                    //add access to this new folder
                    foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                        $db->insert(
                            'roles_values',
                            array(
                                'role_id' => $role,
                                'folder_id' => $newId
                            )
                        );
                    }
                }

                //If it is a subfolder, then give access to it for all roles that allows the parent folder
                $rows = $db->rawQuery(
                    "SELECT role_id
                    FROM ".$pre."roles_values
                    WHERE folder_id = ?",
                    array(
                        $parentId
                    )
                );
                foreach ($rows as $reccord) {
                    //add access to this subfolder
                    $db->insert(
                        'roles_values',
                        array(
                            'role_id' => $reccord['role_id'],
                            'folder_id' => $newId
                       )
                    );
                }
            }
            echo '[ { "error" : "'.$error.'" } ]';

            break;

        //CASE where UPDATING a new group
        case "update_folder":
            $error = "";

            //decrypt and retreive data in JSON format
            $dataReceived = json_decode(
                Encryption\Crypt\aesctr::decrypt($_POST['data'], $_SESSION['key'], 256),
                true
            );

            //Prepare variables
            $title = htmlspecialchars_decode($dataReceived['title']);
            $complexity = htmlspecialchars_decode($dataReceived['complexity']);
            $parentId = htmlspecialchars_decode($dataReceived['parent_id']);
            $renewalPeriod = htmlspecialchars_decode($dataReceived['renewal_period']);

            //Check if title doesn't contains html codes
            if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $title, $out)) {
                echo '[ { "error" : "error_html_codes" } ]';
                break;
            }

            //Check if duplicate folders name are allowed
            $createNewFolder = true;
            if (isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 0) {
                $data = $db->rawQuery(
                    "SELECT id, title 
                    FROM ".$pre."nested_tree 
                    WHERE title = ?",
                    array(
                        addslashes($title)
                    ),
                    true
                );
                if (!empty($data['id']) && $dataReceived['id'] != $data['id'] && $title != $data['title'] ) {
                    echo '[ { "error" : "error_group_exist" } ]';
                    break;
                }
            }

            $db->where("id", $dataReceived['id']);
            $db->update(
                "nested_tree",
                array(
                    'parent_id' => $parentId,
                    'title' => $title,
                    'personal_folder' => 0,
                    'renewal_period' => $renewalPeriod,
                    'bloquer_creation' => '0',
                    'bloquer_modification' => '0'
                )
            );

            //Add complexity
            $db->where("intitule", $dataReceived['id']);
            $db->where("type", "complex");
            $db->update(
                "misc",
                array(
                    'valeur' => $complexity
                )
            );

            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $tree->rebuild();

            //Get user's rights
            identifyUserRights(implode(";", $_SESSION['groupes_visibles']).';'.$dataReceived['id'], implode(";", $_SESSION['groupes_interdits']), $_SESSION['is_admin'], $_SESSION['fonction_id'], true);

            echo '[ { "error" : "'.$error.'" } ]';
            break;

        //CASE where to update the associated Function
        case "fonction":
            $val = explode(';', $_POST['valeur']);
            $valeur = $_POST['valeur'];
            //Check if ID already exists
            $data = $db->rawQuery(
                "SELECT authorized 
                FROM ".$pre."rights 
                WHERE tree_id = ? AND fonction_id = ?",
                array(
                    $val[0],
                    $val[1]
                ),
                true
            );
            if (empty($data[0])) {
                //Insert into DB
                $db->insert(
                    'rights',
                    array(
                        'tree_id' => $val[0],
                        'fonction_id' => $val[1],
                        'authorized' => 1
                   )
                );
            } else {
                //Update DB
                if ($data[0]==1) {
                    $db->where("id", $val[0]);
                    $db->where("fonction_id", $val[1]);
                    $db->update(
                        'rights',
                        array(
                            'authorized' => 0
                       )
                    );
                } else {
                    $db->where("id", $val[0]);
                    $db->where("fonction_id", $val[1]);
                    $db->update(
                        'rights',
                        array(
                            'authorized' => 1
                       )
                    );
                }
            }
            break;

        // CASE where to authorize an ITEM creation without respecting the complexity
        case "modif_droit_autorisation_sans_complexite":
            $db->where("id", $_POST['id']);
            $db->update(
                'nested_tree',
                array(
                    'bloquer_creation' => $_POST['droit']
               )
            );
            break;

        // CASE where to authorize an ITEM modification without respecting the complexity
        case "modif_droit_modification_sans_complexite":
            $db->where("id", $_POST['id']);
            $db->update(
                'nested_tree',
                array(
                    'bloquer_modification' => $_POST['droit']
               )
            );
            break;
    }
}
