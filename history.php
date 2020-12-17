<?php
/*
 *  Jirafeau, your web file repository
 *  Copyright (C) 2015  Jerome Jutteau <j.jutteau@gmail.com>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
define('JIRAFEAU_ROOT', dirname(__FILE__) . '/');

require(JIRAFEAU_ROOT . 'lib/settings.php');
require(JIRAFEAU_ROOT . 'lib/functions.php');
require(JIRAFEAU_ROOT . 'lib/lang.php');

/* Check if installation is OK. */
if (file_exists(JIRAFEAU_ROOT . 'install.php')
    && !file_exists(JIRAFEAU_ROOT . 'lib/config.local.php')) {
    header('Location: install.php');
    exit;
}

check_errors($cfg);
if (has_error()) {
    require(JIRAFEAU_ROOT . 'lib/template/header.php');
    show_errors();
    require(JIRAFEAU_ROOT . 'lib/template/footer.php');
    exit;
}

/* Unlog if asked. */
if (jirafeau_user_session_logged() && isset($_POST['action']) && (strcmp($_POST['action'], 'logout') == 0)) {
    jirafeau_user_session_end();
}
/* Operations may take a long time.
* Be sure PHP's safe mode is off.
*/
@set_time_limit(0);
/* Remove errors. */
@error_reporting(0);

if (!$cfg['http_auth_user'] || empty($_SERVER['PHP_AUTH_USER'])) {
    require(JIRAFEAU_ROOT . 'lib/template/header.php'); ?>
    <div class="info">
    <h2>Not authorized</h2>
    <?php
    require(JIRAFEAU_ROOT . 'lib/template/footer.php');
    exit;
}


if (!jirafeau_user_session_logged()) {
    jirafeau_user_session_start();
}

/* Show user history interface if not downloading a file. */
if (!(isset($_POST['action']) && strcmp($_POST['action'], 'download') == 0)) {
    require(JIRAFEAU_ROOT . 'lib/template/header.php'); ?><h2><?php echo t('ADMIN_INTERFACE'); ?></h2><?php
        ?><h2>(version <?php echo JIRAFEAU_VERSION ?>)</h2><?php

        ?><div id = "admin">
        <fieldset><legend><?php echo t('ACTIONS'); ?></legend>
        <table>
        <form method="post">
        <tr>
            <input type = "hidden" name = "action" value = "clean"/>
            <?php echo jirafeau_user_csrf_field() ?>
            <td class = "info">
                <?php echo t('CLEAN_EXPIRED'); ?>
            </td>
            <td></td>
            <td>
                <input type = "submit" value = "<?php echo t('CLEAN'); ?>" />
            </td>
        </tr>
        </form>
        <form method="post">
        <tr>
            <input type = "hidden" name = "action" value = "clean_async"/>
            <?php echo jirafeau_user_csrf_field() ?>
            <td class = "info">
                <?php echo t('CLEAN_INCOMPLETE'); ?>
            </td>
            <td></td>
            <td>
                <input type = "submit" value = "<?php echo t('CLEAN'); ?>" />
            </td>
        </tr>
        </form>
        <form method="post">
        <tr>
            <input type = "hidden" name = "action" value = "list"/>
            <?php echo jirafeau_user_csrf_field() ?>
            <td class = "info">
                <?php echo t('LS_FILES'); ?>
            </td>
            <td></td>
            <td>
                <input type = "submit" value = "<?php echo t('LIST'); ?>" />
            </td>
        </tr>
        </form>
        <form method="post">
        <tr>
            <input type = "hidden" name = "action" value = "search_by_name"/>
            <?php echo jirafeau_user_csrf_field() ?>
            <td class = "info">
                <?php echo t('SEARCH_NAME'); ?>
            </td>
            <td>
                <input type = "text" name = "name" id = "name"/>
            </td>
            <td>
                <input type = "submit" value = "<?php echo t('SEARCH'); ?>" />
            </td>
        </tr>
        </form>
        <form method="post">
        <tr>
            <input type = "hidden" name = "action" value = "search_by_file_hash"/>
            <?php echo jirafeau_user_csrf_field() ?>
            <td class = "info">
                <?php echo t('SEARH_BY_HASH'); ?>
            </td>
            <td>
                <input type = "text" name = "hash" id = "hash"/>
            </td>
            <td>
                <input type = "submit" value = "<?php echo t('SEARCH'); ?>" />
            </td>
        </tr>
        </form>
        <form method="post">
        <tr>
            <input type = "hidden" name = "action" value = "search_link"/>
            <?php echo jirafeau_user_csrf_field() ?>
            <td class = "info">
                <?php echo t('SEARCH_LINK'); ?>
            </td>
            <td>
                <input type = "text" name = "link" id = "link"/>
            </td>
            <td>
                <input type = "submit" value = "<?php echo t('SEARCH'); ?>" />
            </td>
        </tr>
        </form>
        </table>
        <form method="post">
            <input type = "hidden" name = "action" value = "logout" />
            <?php echo jirafeau_user_csrf_field() ?>
            <input type = "submit" value = "<?php echo t('LOGOUT'); ?>" />
        </form>
        </fieldset></div><?php
}

/* Check for actions */
if (isset($_POST['action'])) {
    if (strcmp($_POST['action'], 'clean') == 0) {
        $total = jirafeau_user_clean();
        echo '<div class="message">' . NL;
        echo '<p>';
        echo t('CLEANED_FILES_CNT') . ' : ' . $total;
        echo '</p></div>';
    } elseif (strcmp($_POST['action'], 'clean_async') == 0) {
        $total = jirafeau_user_clean_async();
        echo '<div class="message">' . NL;
        echo '<p>';
        echo t('CLEANED_FILES_CNT') . ' : ' . $total;
        echo '</p></div>';
    } elseif (strcmp($_POST['action'], 'list') == 0) {
        jirafeau_user_list("", "", "", $_SERVER['PHP_AUTH_USER']);
    } elseif (strcmp($_POST['action'], 'search_by_name') == 0) {
        jirafeau_user_list($_POST['name'], "", "", $_SERVER['PHP_AUTH_USER']);
    } elseif (strcmp($_POST['action'], 'search_by_file_hash') == 0) {
        jirafeau_user_list("", $_POST['hash'], "", $_SERVER['PHP_AUTH_USER']);
    } elseif (strcmp($_POST['action'], 'search_link') == 0) {
        jirafeau_user_list("", "", $_POST['link'], $_SERVER['PHP_AUTH_USER']);
    } elseif (strcmp($_POST['action'], 'delete_link') == 0) {
        jirafeau_delete_link($_POST['link']);
        echo '<div class="message">' . NL;
        echo '<p>' . t('LINK_DELETED') . '</p></div>';
    } elseif (strcmp($_POST['action'], 'delete_file') == 0) {
        $count = jirafeau_delete_file($_POST['md5']);
        echo '<div class="message">' . NL;
        echo '<p>' . t('DELETED_LINKS') . ' : ' . $count . '</p></div>';
    } elseif (strcmp($_POST['action'], 'download') == 0) {
        $l = jirafeau_get_link($_POST['link']);
        if (!count($l)) {
            return;
        }
        $p = s2p($l['md5']);
        header('Content-Length: ' . $l['file_size']);
        header('Content-Type: ' . $l['mime_type']);
        header('Content-Disposition: attachment; filename="' .
                $l['file_name'] . '"');
        if (file_exists(VAR_FILES . $p . $l['md5'])) {
            $r = fopen(VAR_FILES . $p . $l['md5'], 'r');
            while (!feof($r)) {
                print fread($r, 1024);
                ob_flush();
            }
            fclose($r);
        }
        exit;
    }
}

require(JIRAFEAU_ROOT.'lib/template/footer.php');
?>
