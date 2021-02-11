<?php
/*
 *  Jirafeau, your web file repository
 *  Copyright (C) 2013
 *  Jerome Jutteau <jerome@jutteau.fr>
 *  Jimmy Beauvois <jimmy.beauvois@gmail.com>
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
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
session_start();
define('JIRAFEAU_ROOT', dirname(__FILE__) . '/');

require(JIRAFEAU_ROOT . 'lib/settings.php');
require(JIRAFEAU_ROOT . 'lib/functions.php');
require(JIRAFEAU_ROOT . 'lib/lang.php');

check_errors($cfg);
if (has_error()) {
    show_errors();
    require(JIRAFEAU_ROOT . 'lib/template/footer.php');
    exit;
}

require(JIRAFEAU_ROOT . 'lib/template/header.php');

/* Check if user is allowed to upload. */
// First check: Challenge by IP NO PASSWORD
if (true === jirafeau_challenge_upload_ip_without_password($cfg, get_ip_address($cfg))) {
    $_SESSION['upload_auth'] = true;
    $_POST['upload_password'] = '';
    $_SESSION['user_upload_password'] = $_POST['upload_password'];
}
// Second check: Challenge by IP
elseif (true === jirafeau_challenge_upload_ip($cfg, get_ip_address($cfg))) {
    // Is an upload password required?
    if (jirafeau_has_upload_password($cfg)) {
        // Logout action
        if (isset($_POST['action']) && (strcmp($_POST['action'], 'logout') == 0)) {
            session_unset();
        }

        // Challenge by password
        // …save successful logins in session
        if (isset($_POST['upload_password'])) {
            if (jirafeau_challenge_upload_password($cfg, $_POST['upload_password'])) {
                $_SESSION['upload_auth'] = true;
                $_SESSION['user_upload_password'] = $_POST['upload_password'];
            } else {
                $_SESSION['admin_auth'] = false;
                jirafeau_fatal_error(t('BAD_PSW'), $cfg);
            }
        }

        // Show login form if user session is not authorized yet
        if (true === empty($_SESSION['upload_auth'])) {
            ?>
            <form method="post" class="form login">
            <fieldset>
                <table>
                <tr>
                    <td class = "label"><label for = "enter_password">
                    <?php echo t('UP_PSW') . ':'; ?></label>
                    </td>
                </tr><tr>
                    <td class = "field"><input type = "password"
                    name = "upload_password" id = "upload_password"
                    size = "40" />
                    </td>
                </tr>
                <tr class = "nav">
                    <td class = "nav next">
                    <input type = "submit" name = "key" value =
                    "<?php echo t('LOGIN'); ?>" />
                    </td>
                </tr>
                </table>
            </fieldset>
            </form>
            <?php
            require(JIRAFEAU_ROOT.'lib/template/footer.php');
            exit;
        }
    }
} else {
    jirafeau_fatal_error(t('ACCESS_KO'), $cfg);
}

?>
<div id="upload_finished">
    <p><?php echo t('FILE_UP') ?></p>

    <div id="upload_finished_download_page">
    <p>
        <a id="upload_link" href=""><?php echo t('DL_PAGE') ?></a>
        <a id="upload_link_email" href=""><img id="upload_image_email"/></a>
    </p><p>
        <code id=upload_link_text></code>
        <button id="upload_link_button" onclick="addClass()">&#128203;</button>
    </p>
    </div>

    <?php if ($cfg['preview'] == true) {
    ?>
    <div id="upload_finished_preview">
    <p>
        <a id="preview_link" href=""><?php echo t('VIEW_LINK') ?></a>
    </p><p>
        <code id=preview_link_text></code>
        <button id="preview_link_button">&#128203;</button>
    </p>
    </div>
    <?php
} ?>

    <div id="upload_direct_download">
    <p>
        <a id="direct_link" href=""><?php echo t('DIRECT_DL') ?></a>
    </p><p>
        <code id=direct_link_text></code>
        <button id="direct_link_button">&#128203;</button>
    </p>
    </div>

    <div id="upload_delete">
    <p>
        <a id="delete_link" href=""><?php echo t('DELETE_LINK') ?></a>
    </p><p>
        <code id=delete_link_text></code>
        <button id="delete_link_button">&#128203;</button>
    </p>
    </div>

    <div id="upload_validity">
    <p><?php echo t('VALID_UNTIL'); ?>:</p>
    <p id="date"></p>
    </div>

    <!--- Form send mail -->
    <div id="ConteneurFormMail">
        <form name="FormMail" action="" method="post" id="form">
            <div id="data">
                <textarea name="filename" id="filename"></textarea>
                <textarea name="expireDate" id="expireDate"></textarea>
                <textarea name="link" id="link"></textarea>
                <textarea name="password" id="password"></textarea>
            </div>
            <input name="transmitter" type="text" placeholder="<?php echo t('TRANSMITTER'); ?>" id="transmitter" required/>
            <input name="recipient" type="text" placeholder="<?php echo t('RECIPIENT'); ?>" id="recipient" required/>
            <span id="champs_1"><a href="javascript:create_champ(1)">+</a></span>
            <input name="subject" id="subject" placeholder="<?php echo t('MAIL_SUBJECT'); ?>" required></input>
            <textarea name="message" id="message" placeholder="<?php echo t('MESSAGE'); ?>" rows="5" cols="33"></textarea>
            <button id="button_send_mail" onclick="getDataFormMail(event)" required>Send</button>
        </form>
    </div>
</div>

<div id="uploading">
    <p>
    <?php echo t('UP'); ?>
    <div id="uploaded_percentage"></div>
    <div id="uploaded_speed"></div>
    <div id="uploaded_time"></div>
    </p>
</div>

<div id="error_pop" class="error">
</div>

<div id="upload">
<fieldset>
    <legend>
    <?php echo t('SEL_FILE'); ?>
    </legend>
    <p>
        <input type="file" id="file_select" size="30"
    onchange="control_selected_file_size(<?php echo $cfg['maximal_upload_size'] ?>, '<?php
        if ($cfg['maximal_upload_size'] >= 1024) {
            echo t('2_BIG') . ', ' . t('FILE_LIM') . " " . number_format($cfg['maximal_upload_size']/1024, 2) . " GB.";
        } elseif ($cfg['maximal_upload_size'] > 0) {
            echo t('2_BIG') . ', ' . t('FILE_LIM') . " " . $cfg['maximal_upload_size'] . " MB.";
        }
    ?>')"/>
    </p>

    <div id="options">
        <table id="option_table">
        <?php
        if ($cfg['one_time_download']) {
            echo '<tr><td>' . t('ONE_TIME_DL') . ':</td>';
            echo '<td><input type="checkbox" id="one_time_download" /></td></tr>';
        }
        ?>
        <tr>
        <td><label for="input_key"><?php echo t('PSW') . ':'; ?></label></td>
        <td><input type="password" name="key" id="input_key" /></td>
        </tr>
        <tr>
        <td><label for="select_time"><?php echo t('TIME_LIM') . ':'; ?></label></td>
        <td><select name="time" id="select_time">
        <?php
        $expirationTimeOptions = array(
          array(
            'value' => 'minute',
            'label' => '1_MIN'
          ),
          array(
            'value' => 'hour',
            'label' => '1_H'
          ),
          array(
            'value' => 'day',
            'label' => '1_D'
          ),
          array(
            'value' => 'week',
            'label' => '1_W'
          ),
          array(
            'value' => 'month',
            'label' => '1_M'
          ),
          array(
            'value' => 'quarter',
            'label' => '1_Q'
          ),
          array(
            'value' => 'year',
            'label' => '1_Y'
          ),
          array(
            'value' => 'none',
            'label' => 'NONE'
          )
        );
        foreach ($expirationTimeOptions as $expirationTimeOption) {
            $selected = ($expirationTimeOption['value'] === $cfg['availability_default'])? 'selected="selected"' : '';
            if (true === $cfg['availabilities'][$expirationTimeOption['value']]) {
                echo '<option value="' . $expirationTimeOption['value'] . '" ' .
              $selected . '>' . t($expirationTimeOption['label']) . '</option>';
            }
        }
        ?>
        </select></td>
        </tr>

        <?php
        if ($cfg['maximal_upload_size'] >= 1024) {
            echo '<p class="config">' . t('FILE_LIM');
            echo " " . number_format($cfg['maximal_upload_size'] / 1024, 2) . " GB.</p>";
        } elseif ($cfg['maximal_upload_size'] > 0) {
            echo '<p class="config">' . t('FILE_LIM');
            echo " " . $cfg['maximal_upload_size'] . " MB.</p>";
        } else {
            echo '<p class="config"></p>';
        }
        ?>

        <p id="max_file_size" class="config"></p>
    <p>
    <?php
    if (jirafeau_has_upload_password($cfg) && $_SESSION['upload_auth']) {
        ?>
    <input type="hidden" id="upload_password" name="upload_password" value="<?php echo $_SESSION['user_upload_password'] ?>"/>
    <?php
    } else {
        ?>
    <input type="hidden" id="upload_password" name="upload_password" value=""/>
    <?php
    }
    ?>
    <input type="submit" id="send" value="<?php echo t('SEND'); ?>"
    onclick="
        document.getElementById('upload').style.display = 'none';
        document.getElementById('uploading').style.display = '';
        upload (<?php echo jirafeau_get_max_upload_size_bytes(); ?>);
        getPassword();
    "/>
    </p>
        </table>
    </div> </fieldset>

    <?php
    if (jirafeau_has_upload_password($cfg)
        && false === jirafeau_challenge_upload_ip_without_password($cfg, get_ip_address($cfg))) {
        ?>
    <form method="post" class="form logout">
        <input type = "hidden" name = "action" value = "logout"/>
        <input type = "submit" value = "<?php echo t('LOGOUT'); ?>" />
    </form>
    <?php
    }
    ?>

</div>

<?php
if (!empty($cfg['http_auth_user']) && $_SERVER['PHP_AUTH_USER']) {
        ?>
<div id="history">
    <a id="history_link" href="history.php"><?php echo t('HISTORY') ?></a>
</div>
<?php
    }
?>

<script type="text/javascript" lang="Javascript">
// @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPL-v3-or-Later
    document.getElementById('error_pop').style.display = 'none';
    document.getElementById('uploading').style.display = 'none';
    document.getElementById('upload_finished').style.display = 'none';
    document.getElementById('options').style.display = 'none';
    document.getElementById('send').style.display = 'none';
    if (!check_html5_file_api ())
        document.getElementById('max_file_size').innerHTML = '<?php
             echo t('NO_BROWSER_SUPPORT') . jirafeau_get_max_upload_size();
             ?>';

    addCopyListener('upload_link_button', 'upload_link');
    addCopyListener('preview_link_button', 'preview_link');
    addCopyListener('direct_link_button', 'direct_link');
    addCopyListener('delete_link_button', 'delete_link');
// @license-end
</script>
<?php require(JIRAFEAU_ROOT . 'lib/template/footer.php'); ?>
