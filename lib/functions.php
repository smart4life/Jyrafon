<?php
/*
 *  Jirafeau, your web file repository
 *  Copyright (C) 2008  Julien "axolotl" BERNARD <axolotl@magieeternelle.org>
 *  Copyright (C) 2015  Jerome Jutteau <jerome@jutteau.fr>
 *  Copyright (C) 2015  Nicola Spanti (RyDroid) <dev@nicola-spanti.info>
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

//Definition of encryption types (enums)
define('encrypt_openssl', 'encrypt_openssl');
define('encrypt_mcrypt', 'encrypt_mcrypt');
define('encrypt_autocrypt', 'encrypt_autocrypt');
/**
 * Transform a string in a path by seperating each letters by a '/'.
 * @return path finishing with a '/'
 */
function s2p($s)
{
    $block_size = 8;
    $p = '';
    for ($i = 0; $i < strlen($s); $i++) {
        $p .= $s{$i};
        if (($i + 1) % $block_size == 0) {
            $p .= '/';
        }
    }
    if (strlen($s) % $block_size != 0) {
        $p .= '/';
    }
    return $p;
}

/**
 * Convert base 16 to base 64
 * @returns A string based on 64 characters (0-9, a-z, A-Z, "-" and "_")
 */
function base_16_to_64($num)
{
    $m = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_';
    $hex2bin = array('0000',  # 0
                      '0001',  # 1
                      '0010',  # 2
                      '0011',  # 3
                      '0100',  # 4
                      '0101',  # 5
                      '0110',  # 6
                      '0111',  # 7
                      '1000',  # 8
                      '1001',  # 9
                      '1010',  # a
                      '1011',  # b
                      '1100',  # c
                      '1101',  # d
                      '1110',  # e
                      '1111'); # f
    $o = '';
    $b = '';
    $i = 0;
    # Convert long hex string to bin.
    $size = strlen($num);
    for ($i = 0; $i < $size; $i++) {
        $b .= $hex2bin{hexdec($num{$i})};
    }
    # Convert long bin to base 64.
    $size *= 4;
    for ($i = $size - 6; $i >= 0; $i -= 6) {
        $o = $m{bindec(substr($b, $i, 6))} . $o;
    }
    # Some few bits remaining ?
    if ($i < 0 && $i > -6) {
        $o = $m{bindec(substr($b, 0, $i + 6))} . $o;
    }
    return $o;
}

/**
  * Generate a random code.
  * @param $l code length
  * @return  random code.
  */
function jirafeau_gen_random($l)
{
    if ($l <= 0) {
        return 42;
    }

    $code="";
    for ($i = 0; $i < $l; $i++) {
        $code .= dechex(rand(0, 15));
    }

    return $code;
}

function is_ssl()
{
    if (isset($_SERVER['HTTPS'])) {
        if ('on' == strtolower($_SERVER['HTTPS']) ||
             '1' == $_SERVER['HTTPS']) {
            return true;
        }
    } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
        return true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        if ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            return true;
        }
    }
    return false;
}

function jirafeau_human_size($octets)
{
    $u = array('B', 'KB', 'MB', 'GB', 'TB');
    $o = max($octets, 0);
    $p = min(floor(($o ? log($o) : 0) / log(1024)), count($u) - 1);
    $o /= pow(1024, $p);
    return round($o, 1) . $u[$p];
}

// Convert UTC timestamp to a datetime field
function jirafeau_get_datetimefield($timestamp)
{
    $content = '<span class="datetime" data-datetime="' . strftime('%Y-%m-%d %H:%M', $timestamp) . '">'
        . strftime('%Y-%m-%d %H:%M', $timestamp) . ' (GMT)</span>';
    return $content;
}

function jirafeau_fatal_error($errorText, $cfg = array())
{
    echo '<div class="error"><h2>Error</h2><p>' . $errorText . '</p></div>';
    require(JIRAFEAU_ROOT . 'lib/template/footer.php');
    exit;
}

function jirafeau_clean_rm_link($link)
{
    $p = s2p("$link");
    if (file_exists(VAR_LINKS . $p . $link)) {
        unlink(VAR_LINKS . $p . $link);
    }
    $parse = VAR_LINKS . $p;
    $scan = array();
    while (file_exists($parse)
           && ($scan = scandir($parse))
           && count($scan) == 2 // '.' and '..' folders => empty.
           && basename($parse) != basename(VAR_LINKS)) {
        rmdir($parse);
        $parse = substr($parse, 0, strlen($parse) - strlen(basename($parse)) - 1);
    }
}

function jirafeau_clean_rm_file($hash)
{
    $p = s2p("$hash");
    $f = VAR_FILES . $p . $hash;
    if (file_exists($f) && is_file($f)) {
        unlink($f);
    }
    if (file_exists($f . '_count') && is_file($f . '_count')) {
        unlink($f . '_count');
    }
    $parse = VAR_FILES . $p;
    $scan = array();
    while (file_exists($parse)
           && ($scan = scandir($parse))
           && count($scan) == 2 // '.' and '..' folders => empty.
           && basename($parse) != basename(VAR_FILES)) {
        rmdir($parse);
        $parse = substr($parse, 0, strlen($parse) - strlen(basename($parse)) - 1);
    }
}

/**
 * transforms a php.ini string representing a value in an integer
 * @param $value the value from php.ini
 * @returns an integer for this value
 */
function jirafeau_ini_to_bytes($value)
{
    $modifier = substr($value, -1);
    $bytes = substr($value, 0, -1);
    switch (strtoupper($modifier)) {
    case 'P':
        $bytes *= 1024;
        // no break
    case 'T':
        $bytes *= 1024;
        // no break
    case 'G':
        $bytes *= 1024;
        // no break
    case 'M':
        $bytes *= 1024;
        // no break
    case 'K':
        $bytes *= 1024;
    }
    return $bytes;
}

/**
 * gets the maximum upload size according to php.ini
 * @returns the maximum upload size in bytes
 */
function jirafeau_get_max_upload_size_bytes()
{
    return min(
        jirafeau_ini_to_bytes(ini_get('post_max_size')),
        jirafeau_ini_to_bytes(ini_get('upload_max_filesize'))
    );
}

/**
 * gets the maximum upload size according to php.ini
 * @returns the maximum upload size string
 */
function jirafeau_get_max_upload_size()
{
    return jirafeau_human_size(jirafeau_get_max_upload_size_bytes());
}

/**
 * gets a string explaining the error
 * @param $code the error code
 * @returns a string explaining the error
 */
function jirafeau_upload_errstr($code)
{
    switch ($code) {
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        return t('Your file exceeds the maximum authorized file size. ');

    case UPLOAD_ERR_PARTIAL:
    case UPLOAD_ERR_NO_FILE:
        return
            t('Your file was not uploaded correctly. You may succeed in retrying. ');

    case UPLOAD_ERR_NO_TMP_DIR:
    case UPLOAD_ERR_CANT_WRITE:
    case UPLOAD_ERR_EXTENSION:
        return t('Internal error. You may not succeed in retrying. ');
    }
    return t('Unknown error. ');
}

/** Remove link and it's file
 * @param $link the link's name (hash)
 */

function jirafeau_delete_link($link)
{
    $l = jirafeau_get_link($link);
    if (!count($l)) {
        return;
    }

    jirafeau_clean_rm_link($link);

    $hash = $l['hash'];
    $p = s2p("$hash");

    $counter = 1;
    if (file_exists(VAR_FILES . $p . $hash. '_count')) {
        $content = file(VAR_FILES . $p . $hash. '_count');
        $counter = trim($content[0]);
    }
    $counter--;

    if ($counter >= 1) {
        $handle = fopen(VAR_FILES . $p . $hash. '_count', 'w');
        fwrite($handle, $counter);
        fclose($handle);
    }

    if ($counter == 0) {
        jirafeau_clean_rm_file($hash);
    }
}

/**
 * Delete a file and it's links.
 */
function jirafeau_delete_file($hash)
{
    $count = 0;
    /* Get all links files. */
    $stack = array(VAR_LINKS);
    while (($d = array_shift($stack)) && $d != null) {
        $dir = scandir($d);

        foreach ($dir as $node) {
            if (strcmp($node, '.') == 0 || strcmp($node, '..') == 0 ||
                preg_match('/\.tmp/i', "$node")) {
                continue;
            }

            if (is_dir($d . $node)) {
                /* Push new found directory. */
                $stack[] = $d . $node . '/';
            } elseif (is_file($d . $node)) {
                /* Read link informations. */
                $l = jirafeau_get_link(basename($node));
                if (!count($l)) {
                    continue;
                }
                if ($l['hash'] == $hash) {
                    $count++;
                    jirafeau_delete_link($node);
                }
            }
        }
    }
    jirafeau_clean_rm_file($hash);
    return $count;
}


/** hash file's content
 * @param $method hash method, see 'file_hash' option. Valid methods are 'md5', 'md5_outside' or 'random'
 * @param $file_path file to hash
 * @returns hash string
 */
function jirafeau_hash_file($method, $file_path)
{
    switch ($method) {
        case 'md5_outside':
            return jirafeau_md5_outside($file_path);
        case 'md5':
            return md5_file($file_path);
        case 'random':
            return jirafeau_gen_random(32);
    }
    return md5_file($file_path);
}

/** hash part of file: start, end and size.
 * This is a partial file hash, faster but weaker.
 * @param $file_path file to hash
 * @returns hash string
 */
function jirafeau_md5_outside($file_path)
{
    $out = false;
    $handle = fopen($file_path, "r");
    if ($handle === false) {
        return false;
    }
    $size = filesize($file_path);
    if ($size === false) {
        goto err;
    }
    $first = fread($handle, 64);
    if ($first === false) {
        goto err;
    }
    if (fseek($handle, $size < 64 ? 0 : $size - 64) == -1) {
        goto err;
    }
    $last = fread($handle, 64);
    if ($last === false) {
        goto err;
    }
    $out = md5($first . $last . $size);
    err:
    fclose($handle);
    return $out;
}

/** Determinate type of encryption
 * @param array $cfg application configuration, see config.original.php
 * @returns the options of encryption
 */
function determinateOptionEncrypt($cfg)
{
    /* Determinate type of encryption */
    switch ($cfg['crypt']) {
        case encrypt_mcrypt:
            $option = 'C';
            return $option;
            break;
        case encrypt_openssl:
            $option = 'S';
            return $option;
            break;
        case encrypt_autocrypt:
            if (extension_loaded('mcrypt') == true) {
                $option = 'AC'; // Auto encrypt with mcrypt
                return $option;
            } elseif (extension_loaded('openssl') == true && extension_loaded('mcrypt') == false) {
                $option = 'AS'; // Auto encrypt with openssl
                return $option;
            }
            break;
        default:
            error_log("PHP extension: incorrect syntax in 'crypt', see config.local.php, won't encrypt in Jirafeau");
            break;
    }
}

/** Determinate encryption file
 * @param array $cfg application configuration, see config.original.php
 * @returns boolean
 */
function determinateCrypt($cfg)
{
    switch ($cfg['enable_crypt']) {
        case true:
            $optionCrypt = determinateOptionEncrypt($cfg);
            return $optionCrypt;
            break;
        case false:
            error_log("PHP extension: Encryption is not enable, won't encrypt in Jirafeau");
            return '';
            break;
    }
}

/**
 * handles an uploaded file
 * @param $file the file struct given by $_FILE[]
 * @param $one_time_download is the file a one time download ?
 * @param $key if not empty, protect the file with this key
 * @param $time the time of validity of the file
 * @param $ip uploader's ip
 * @param $crypt boolean asking to crypt or not
 * @param $link_name_length size of the link name
 * @returns an array containing some information
 *   'error' => information on possible errors
 *   'link' => the link name of the uploaded file
 *   'delete_link' => the link code to delete file
 */
function jirafeau_upload($file, $one_time_download, $key, $time, $ip, $cfg, $link_name_length, $file_hash_method, $user_name = '')
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return (array(
                 'error' =>
                   array('has_error' => true,
                          'why' => jirafeau_upload_errstr($file['error'])),
                 'link' => '',
                 'delete_link' => ''));
    }

    /* array representing no error */
    $noerr = array('has_error' => false, 'why' => '');

    /* Crypt file if option is enabled. */
    $crypted = false;
    $crypt_key = '';
    $option = determinateCrypt($cfg);
    if (strlen($option) > 0) {
        /* Determinate type of encryption */
        switch ($cfg['crypt']) {
            case encrypt_mcrypt:
                if (extension_loaded('mcrypt') == true) {
                    $crypt_key = jirafeau_encrypt_file($file['tmp_name'], $file['tmp_name']);
                    if (strlen($crypt_key) > 0) {
                        $crypted = true;
                    }
                } else {
                    error_log("PHP extension: mcrypt not loaded, won't encrypt in Jirafeau");
                }
                break;
            case encrypt_openssl:
                if (extension_loaded('openssl') == true) {
                    $key = jirafeau_gen_random(10);
                    $crypt_key = jirafeau_encrypt_file_openssl($file['tmp_name'], $key, $file['tmp_name'], $cfg);
                    if (strlen($crypt_key) > 0) {
                        $crypted = true;
                    }
                } else {
                    error_log("PHP extension: openssl not loaded, won't encrypt in Jirafeau");
                }
                break;
            case encrypt_autocrypt:
                if (extension_loaded('mcrypt') == true) {
                    $crypt_key = jirafeau_encrypt_file($file['tmp_name'], $file['tmp_name']);
                    if (strlen($crypt_key) > 0) {
                        $crypted = true;
                    }
                } else if (extension_loaded('openssl') == true && extension_loaded('mcrypt') == false) {
                    $key = jirafeau_gen_random(10);
                    $crypt_key = jirafeau_encrypt_file_openssl($file['tmp_name'], $key, $file['tmp_name'], $cfg);
                    if (strlen($crypt_key) > 0) {
                        $crypted = true;
                    }
                } else {
                    error_log("PHP extension; openssl and mcrypt are not loaded, won't encrypt in Jirafeau");
                }
                break;
            default:
                error_log("PHP extension; incorrect syntax in 'crypt', see config.local.php, won't encrypt in Jirafeau");
                break;
        }
    }
    /* file informations */
    $hash = jirafeau_hash_file($file_hash_method, $file['tmp_name']);
    $name = str_replace(NL, '', trim($file['name']));
    $mime_type = $file['type'];
    $size = $file['size'];

    /* does file already exist ? */
    $rc = false;
    $p = s2p("$hash");
    if (file_exists(VAR_FILES . $p .  $hash)) {
        $rc = unlink($file['tmp_name']);
    } elseif ((file_exists(VAR_FILES . $p) || @mkdir(VAR_FILES . $p, 0755, true))
            && move_uploaded_file($file['tmp_name'], VAR_FILES . $p . $hash)) {
        $rc = true;
    }
    if (!$rc) {
        return (array(
                 'error' =>
                   array('has_error' => true,
                          'why' => t('INTERNAL_ERROR_DEL')),
                 'link' =>'',
                 'delete_link' => ''));
    }

    /* Increment or create count file. */
    $counter = 0;
    if (file_exists(VAR_FILES . $p . $hash . '_count')) {
        $content = file(VAR_FILES . $p . $hash. '_count');
        $counter = trim($content[0]);
    }
    $counter++;
    $handle = fopen(VAR_FILES . $p . $hash. '_count', 'w');
    fwrite($handle, $counter);
    fclose($handle);

    /* Create delete code. */
    $delete_link_code = jirafeau_gen_random(5);

    /* hash password or empty. */
    $password = '';
    if (!empty($key)) {
        $password = md5($key);
    }

    /* create link file */
    $link_tmp_name =  VAR_LINKS . $hash . rand(0, 10000) . '.tmp';
    $handle = fopen($link_tmp_name, 'w');

    /*File encrypt with openssl ? */
    fwrite(
        $handle,
        $name . NL. $mime_type . NL. $size . NL. $password . NL. $time .
            NL . $hash. NL . ($one_time_download ? 'O' : 'R') . NL . time() .
            NL . $ip . NL. $delete_link_code . NL . ($crypted ? $option : 'O') .
            NL . $user_name
    );
    fclose($handle);
    $hash_link = substr(base_16_to_64(md5_file($link_tmp_name)), 0, $link_name_length);
    $l = s2p("$hash_link");
    if (!@mkdir(VAR_LINKS . $l, 0755, true) ||
        !rename($link_tmp_name, VAR_LINKS . $l . $hash_link)) {
        if (file_exists($link_tmp_name)) {
            unlink($link_tmp_name);
        }

        $counter--;
        if ($counter >= 1) {
            $handle = fopen(VAR_FILES . $p . $hash. '_count', 'w');
            fwrite($handle, $counter);
            fclose($handle);
        } else {
            jirafeau_clean_rm_file($hash_link);
        }
        return array(
                 'error' =>
                   array('has_error' => true,
                          'why' => t('Internal error during file creation. ')),
                 'link' =>'',
                 'delete_link' => '');
    }
    return array( 'error' => $noerr,
                  'link' => $hash_link,
                  'delete_link' => $delete_link_code,
                  'crypt_key' => $crypt_key);
}

/**
 * Tells if a mime-type is viewable in a browser
 * @param $mime the mime type
 * @returns a boolean telling if a mime type is viewable
 */
function jirafeau_is_viewable($mime)
{
    if (!empty($mime)) {
        /* Actually, verify if mime-type is an image or a text. */
        $viewable = array('image', 'text', 'video', 'audio');
        $decomposed = explode('/', $mime);
        return in_array($decomposed[0], $viewable);
    }
    return false;
}

// Error handling functions.
//! Global array that contains all registered errors.
$error_list = array();

/**
 * Adds an error to the list of errors.
 * @param $title the error's title
 * @param $description is a human-friendly description of the problem.
 */
function add_error($title, $description)
{
    global $error_list;
    $error_list[] = '<p>' . $title. '<br />' . $description. '</p>';
}

/**
 * Informs whether any error has been registered yet.
 * @return true if there are errors.
 */
function has_error()
{
    global $error_list;
    return !empty($error_list);
}

/**
 * Displays all the errors.
 */
function show_errors()
{
    if (has_error()) {
        global $error_list;
        echo '<div class="error">';
        foreach ($error_list as $error) {
            echo $error;
        }
        echo '</div>';
    }
}

function check_errors($cfg)
{
    if (file_exists(JIRAFEAU_ROOT . 'install.php')
        && !($cfg['installation_done'] === true)) {
        header('Location: install.php');
        exit;
    }

    /* Checking for errors. */
    if (!is_writable(VAR_FILES)) {
        add_error(t('FILE_DIR_W'), VAR_FILES);
    }

    if (!is_writable(VAR_LINKS)) {
        add_error(t('LINK_DIR_W'), VAR_LINKS);
    }

    if (!is_writable(VAR_ASYNC)) {
        add_error(t('ASYNC_DIR_W'), VAR_ASYNC);
    }

    if ($cfg['enable_crypt'] && $cfg['litespeed_workaround']) {
        add_error(t('INCOMPATIBLE_OPTIONS_W'), 'enable_crypt=true<br>litespeed_workaround=true');
    }

    if ($cfg['one_time_download'] && $cfg['litespeed_workaround']) {
        add_error(t('INCOMPATIBLE_OPTIONS_W'), 'one_time_download=true<br>litespeed_workaround=true');
    }
}

/**
 * Read link informations
 * @return array containing informations.
 */
function jirafeau_get_link($hash)
{
    $out = array();
    $link = VAR_LINKS . s2p("$hash") . $hash;

    if (!file_exists($link)) {
        return $out;
    }

    $c = file($link);
    $out['file_name'] = trim($c[0]);
    $out['mime_type'] = trim($c[1]);
    $out['file_size'] = trim($c[2]);
    $out['key'] = trim($c[3], NL);
    $out['time'] = trim($c[4]);
    $out['hash'] = trim($c[5]);
    $out['onetime'] = trim($c[6]);
    $out['upload_date'] = trim($c[7]);
    $out['ip'] = trim($c[8]);
    $out['link_code'] = trim($c[9]);
    $out['crypted'] = trim($c[10]) == 'C';
    $out['user_name'] = trim($c[11]);

    return $out;
}

function jirafeau_user_list($name, $file_hash, $link_hash, $user_name)
{
    jirafeau_list($name, $file_hash, $link_hash, true, $user_name);
}

function jirafeau_admin_list($name, $file_hash, $link_hash)
{
    jirafeau_list($name, $file_hash, $link_hash, false, null);
}

/**
 * List files in admin interface.
 */
function jirafeau_list($name, $file_hash, $link_hash, $use_user_name, $user_name)
{
    echo '<fieldset><legend>';
    if (!empty($name)) {
        echo t('FILENAME') . ": " . jirafeau_escape($name);
    }
    if (!empty($file_hash)) {
        echo t('FILE') . ": " . jirafeau_escape($file_hash);
    }
    if (!empty($link_hash)) {
        echo t('LINK') . ": " . jirafeau_escape($link_hash);
    }
    if (empty($name) && empty($file_hash) && empty($link_hash)) {
        echo t('LS_FILES');
    }
    echo '</legend>';
    echo '<table>';
    echo '<tr>';
    echo '<th></th>';
    echo '<th>' . t('ACTION') . '</th>';
    echo '</tr>';

    /* Get all links files. */
    $stack = array(VAR_LINKS);
    while (($d = array_shift($stack)) && $d != null) {
        $dir = scandir($d);
        foreach ($dir as $node) {
            if (strcmp($node, '.') == 0 || strcmp($node, '..') == 0 ||
                preg_match('/\.tmp/i', "$node")) {
                continue;
            }
            if (is_dir($d . $node)) {
                /* Push new found directory. */
                $stack[] = $d . $node . '/';
            } elseif (is_file($d . $node)) {
                /* Read link informations. */
                $l = jirafeau_get_link($node);
                if (!count($l)) {
                    continue;
                }

                /* Filter. */
                if ($use_user_name && (empty($l['user_name']) || $l['user_name'] !== $user_name)) {
                    continue;
                }
                if (!empty($name) && !@preg_match("/$name/i", jirafeau_escape($l['file_name']))) {
                    continue;
                }
                if (!empty($file_hash) && $file_hash != $l['hash']) {
                    continue;
                }
                if (!empty($link_hash) && $link_hash != $node) {
                    continue;
                }
                /* Print link informations. */
                echo '<tr>';
                echo '<td>' .
                '<strong><a id="upload_link" href="f.php?h='. jirafeau_escape($node) .'" title="' .
                    t('DL_PAGE') . '">' . jirafeau_escape($l['file_name']) . '</a></strong><br/>';
                echo t('TYPE') . ': ' . jirafeau_escape($l['mime_type']) . '<br/>';
                echo t('SIZE') . ': ' . jirafeau_human_size($l['file_size']) . '<br>';
                echo t('EXPIRE') . ': ' . ($l['time'] == -1 ? '∞' : jirafeau_get_datetimefield($l['time'])) . '<br/>';
                echo t('ONETIME') . ': ' . ($l['onetime'] == 'O' ? 'Yes' : 'No') . '<br/>';
                echo t('UPLOAD_DATE') . ': ' . jirafeau_get_datetimefield($l['upload_date']) . '<br/>';
                if (strlen($l['ip']) > 0) {
                    echo t('ORIGIN') . ': ' . $l['ip'] . '<br/>';
                }
                echo '</td><td>';
                echo '<form method="post">' .
                '<input type = "hidden" name = "action" value = "download"/>' .
                '<input type = "hidden" name = "link" value = "' . $node . '"/>' .
                jirafeau_admin_csrf_field() .
                '<input type = "submit" value = "' . t('DL') . '" />' .
                '</form>' .
                '<form method="post">' .
                '<input type = "hidden" name = "action" value = "delete_link"/>' .
                '<input type = "hidden" name = "link" value = "' . $node . '"/>' .
                jirafeau_admin_csrf_field() .
                '<input type = "submit" value = "' . t('DEL_LINK') . '" />' .
                '</form>' .
                '<form method="post">' .
                '<input type = "hidden" name = "action" value = "delete_file"/>' .
                '<input type = "hidden" name = "hash" value = "' . $l['hash'] . '"/>' .
                jirafeau_admin_csrf_field() .
                '<input type = "submit" value = "' . t('DEL_FILE_LINKS') . '" />' .
                '</form>' .
                '</td>';
                echo '</tr>';
            }
        }
    }
    echo '</table></fieldset>';
}

/**
 * Clean expired files.
 * @return number of cleaned files.
 */
function jirafeau_user_clean($user_name)
{
    return jirafeau_clean(true, $user_name);
}

/**
 * Clean expired files.
 * @return number of cleaned files.
 */
function jirafeau_admin_clean()
{
    return jirafeau_clean(false, null);
}

/**
 * Clean expired files.
 * @return number of cleaned files.
 */
function jirafeau_clean($use_user_name, $user_name)
{
    $count = 0;
    /* Get all links files. */
    $stack = array(VAR_LINKS);
    while (($d = array_shift($stack)) && $d != null) {
        $dir = scandir($d);

        foreach ($dir as $node) {
            if (strcmp($node, '.') == 0 || strcmp($node, '..') == 0 ||
                preg_match('/\.tmp/i', "$node")) {
                continue;
            }

            if (is_dir($d . $node)) {
                /* Push new found directory. */
                $stack[] = $d . $node . '/';
            } elseif (is_file($d . $node)) {
                /* Read link informations. */
                $l = jirafeau_get_link(basename($node));
                if (!count($l)) {
                    continue;
                }
                if ($use_user_name && (empty($l['user_name']) || $l['user_name'] !== $user_name)) {
                    continue;
                }
                $p = s2p($l['hash']);
                if ($l['time'] > 0 && $l['time'] < time() || // expired
                    !file_exists(VAR_FILES . $p . $l['hash']) || // invalid
                    !file_exists(VAR_FILES . $p . $l['hash'] . '_count')) { // invalid
                    jirafeau_delete_link($node);
                    $count++;
                }
            }
        }
    }
    return $count;
}

/**
 * Clean old async transferts.
 * @return number of cleaned files.
 */
function jirafeau_user_clean_async($user_name)
{
    return jirafeau_clean_async(true, $user_name);
}

/**
 * Clean old async transferts.
 * @return number of cleaned files.
 */
function jirafeau_admin_clean_async()
{
    return jirafeau_clean_async(false, null);
}

/**
 * Clean old async transferts.
 * @return number of cleaned files.
 */
function jirafeau_clean_async($use_user_name, $user_name)
{
    $count = 0;
    /* Get all links files. */
    $stack = array(VAR_ASYNC);
    while (($d = array_shift($stack)) && $d != null) {
        $dir = scandir($d);

        foreach ($dir as $node) {
            if (strcmp($node, '.') == 0 || strcmp($node, '..') == 0 ||
                preg_match('/\.tmp/i', "$node")) {
                continue;
            }

            if (is_dir($d . $node)) {
                /* Push new found directory. */
                $stack[] = $d . $node . '/';
            } elseif (is_file($d . $node)) {
                /* Read async informations. */
                $a = jirafeau_get_async_ref(basename($node));
                if (!count($a)) {
                    continue;
                }
                if ($use_user_name && !empty($a['user_name']) && $a['user_name'] !== $user_name) {
                    continue;
                }

                /* Delete transferts older than 1 hour. */
                if (time() - $a['last_edited'] > 3600) {
                    jirafeau_async_delete(basename($node));
                    $count++;
                }
            }
        }
    }
    return $count;
}
/**
 * Read async transfert informations
 * @return array containing informations.
 */
function jirafeau_get_async_ref($ref)
{
    $out = array();
    $refinfos = VAR_ASYNC . s2p("$ref") . "$ref";

    if (!file_exists($refinfos)) {
        return $out;
    }

    $c = file($refinfos);
    $out['file_name'] = trim($c[0]);
    $out['mime_type'] = trim($c[1]);
    $out['key'] = trim($c[2], NL);
    $out['time'] = trim($c[3]);
    $out['onetime'] = trim($c[4]);
    $out['ip'] = trim($c[5]);
    $out['last_edited'] = trim($c[6]);
    $out['next_code'] = trim($c[7]);
    $out['user_name'] = trim($c[8]);
    return $out;
}

/**
 * Delete async transfert informations
 */
function jirafeau_async_delete($ref)
{
    $p = s2p("$ref");
    if (file_exists(VAR_ASYNC . $p . $ref)) {
        unlink(VAR_ASYNC . $p . $ref);
    }
    if (file_exists(VAR_ASYNC . $p . $ref . '_data')) {
        unlink(VAR_ASYNC . $p . $ref . '_data');
    }
    $parse = VAR_ASYNC . $p;
    $scan = array();
    while (file_exists($parse)
           && ($scan = scandir($parse))
           && count($scan) == 2 // '.' and '..' folders => empty.
           && basename($parse) != basename(VAR_ASYNC)) {
        rmdir($parse);
        $parse = substr($parse, 0, strlen($parse) - strlen(basename($parse)) - 1);
    }
}

/**
  * Init a new asynchronous upload.
  * @param $finename Name of the file to send
  * @param $one_time One time upload parameter
  * @param $key eventual password (or blank)
  * @param $time time limit
  * @param $ip ip address of the client
  * @param $user_name Optional user_name of author
  * @return a string containing a temporary reference followed by a code or the string 'Error'
  */
function jirafeau_async_init($filename, $type, $one_time, $key, $time, $ip, $user_name = '')
{
    $res = 'Error';

    /* Create temporary folder. */
    $ref;
    $p;
    $code = jirafeau_gen_random(4);
    do {
        $ref = jirafeau_gen_random(32);
        $p = VAR_ASYNC . s2p($ref);
    } while (file_exists($p));
    @mkdir($p, 0755, true);
    if (!file_exists($p)) {
        echo 'Error';
        return;
    }

    /* touch empty data file */
    $w_path = $p . $ref . '_data';
    touch($w_path);

    /* md5 password or empty */
    $password = '';
    if (!empty($key)) {
        $password = md5($key);
    }

    /* Store informations. */
    $p .= $ref;
    $handle = fopen($p, 'w');
    fwrite(
        $handle,
        str_replace(NL, '', trim($filename)) . NL .
            str_replace(NL, '', trim($type)) . NL . $password . NL .
            $time . NL . ($one_time ? 'O' : 'R') . NL . $ip . NL .
            time() . NL . $code . NL . $user_name . NL
    );
    fclose($handle);

    return $ref . NL . $code ;
}

/**
  * Append a piece of file on the asynchronous upload.
  * @param $ref asynchronous upload reference
  * @param $file piece of data
  * @param $code client code for this operation
  * @param $max_file_size maximum allowed file size
  * @return a string containing a next code to use or the string "Error"
  */
function jirafeau_async_push($ref, $data, $code, $max_file_size)
{
    /* Get async infos. */
    $a = jirafeau_get_async_ref($ref);

    /* Check some errors. */
    if (count($a) == 0
        || $a['next_code'] != "$code"
        || empty($data['tmp_name'])
        || !is_uploaded_file($data['tmp_name'])) {
        return 'Error';
    }

    $p = s2p($ref);

    /* File path. */
    $r_path = $data['tmp_name'];
    $w_path = VAR_ASYNC . $p . $ref . '_data';

    /* Check that file size is not above upload limit. */
    if ($max_file_size > 0 &&
        filesize($r_path) + filesize($w_path) > $max_file_size * 1024 * 1024) {
        jirafeau_async_delete($ref);
        return 'Error';
    }

    /* Concatenate data. */
    $r = fopen($r_path, 'r');
    $w = fopen($w_path, 'a');
    while (!feof($r)) {
        if (fwrite($w, fread($r, 1024)) === false) {
            fclose($r);
            fclose($w);
            jirafeau_async_delete($ref);
            return 'Error';
        }
    }
    fclose($r);
    fclose($w);
    unlink($r_path);

    /* Update async file. */
    $code = jirafeau_gen_random(4);
    $handle = fopen(VAR_ASYNC . $p . $ref, 'w');
    fwrite(
        $handle,
        $a['file_name'] . NL. $a['mime_type'] . NL. $a['key'] . NL .
            $a['time'] . NL . $a['onetime'] . NL . $a['ip'] . NL .
            time() . NL . $code . NL . $a['user_name'] . NL
    );
    fclose($handle);
    return $code;
}

/**
  * Finalyze an asynchronous upload.
  * @param $ref asynchronous upload reference
  * @param $code client code for this operation
  * @param $crypt boolean asking to crypt or not
  * @param $link_name_length link name length
  * @return a string containing the download reference followed by a delete code or the string 'Error'
  */
function jirafeau_async_end($ref, $code, $cfg, $link_name_length, $file_hash_method)
{
    /* Get async infos. */
    $a = jirafeau_get_async_ref($ref);
    if (count($a) == 0
        || $a['next_code'] != "$code") {
        return "Error";
    }

    /* Generate link infos. */
    $p = VAR_ASYNC . s2p($ref) . $ref . "_data";
    if (!file_exists($p)) {
        return 'Error';
    }

    $crypted = false;
    $crypt_key = '';
    $option = determinateCrypt($cfg);
    if (strlen($option) > 0) {
        switch ($cfg['crypt']) {
            case encrypt_openssl:
                if (extension_loaded('openssl') == true && extension_loaded('mcrypt') == false) {
                    $key = jirafeau_gen_random(10);
                    $crypt_key = jirafeau_encrypt_file_openssl($p, $key, $p, $cfg);
                    if (strlen($crypt_key) > 0) {
                        $crypted = true;
                    }
                } else {
                    error_log("PHP extension: openssl is not loaded, won't encrypt in Jirafeau");
                }
                break;
            case encrypt_mcrypt:
                if (extension_loaded('mcrypt') == true && extension_loaded('openssl') == false) {
                    $key = jirafeau_gen_random(10);
                    $crypt_key = jirafeau_encrypt_file($p, $key, $p, $cfg);
                    if (strlen($crypt_key) > 0) {
                        $crypted = true;
                    }
                } else {
                    error_log("PHP extension: mcrypt is not loaded, won't encrypt in Jirafeau");
                }
                break;
            case encrypt_autocrypt:
                if (extension_loaded('mcrypt') == true) {
                    $key = jirafeau_gen_random(10);
                    $crypt_key = jirafeau_encrypt_file($p, $key, $p, $cfg);
                    if (strlen($crypt_key) > 0) {
                        $crypted = true;
                    }
                } elseif (extension_loaded('openssl') == true && extension_loaded('mcrypt') == false) {
                    $key = jirafeau_gen_random(10);
                    $crypt_key = jirafeau_encrypt_file_openssl($p, $key, $p, $cfg);
                    if (strlen($crypt_key) > 0) {
                        $crypted = true;
                    }
                } else {
                    error_log("PHP extension: openssl or mcrypt are not loaded, won't encrypt in Jirafeau");
                }
                break;
            default:
                error_log("PHP extension: incorrect syntax in 'crypt',see config.local.php , won't encrypt in Jirafeau");
                break;
        }
    }
    $hash = jirafeau_hash_file($file_hash_method, $p);
    $size = filesize($p);
    $np = s2p($hash);
    $delete_link_code = jirafeau_gen_random(5);

    /* File already exist ? */
    if (!file_exists(VAR_FILES . $np)) {
        @mkdir(VAR_FILES . $np, 0755, true);
    }
    if (!file_exists(VAR_FILES . $np . $hash)) {
        rename($p, VAR_FILES . $np . $hash);
    }

    /* Increment or create count file. */
    $counter = 0;
    if (file_exists(VAR_FILES . $np . $hash . '_count')) {
        $content = file(VAR_FILES . $np . $hash. '_count');
        $counter = trim($content[0]);
    }
    $counter++;
    $handle = fopen(VAR_FILES . $np . $hash. '_count', 'w');
    fwrite($handle, $counter);
    fclose($handle);

    /* Create link. */
    $link_tmp_name =  VAR_LINKS . $hash . rand(0, 10000) . '.tmp';
    $handle = fopen($link_tmp_name, 'w');

    /* File encrypt with openssl */
    fwrite(
        $handle,
        $a['file_name'] . NL . $a['mime_type'] . NL . $size . NL .
            $a['key'] . NL . $a['time'] . NL . $hash . NL . $a['onetime'] . NL .
            time() . NL . $a['ip'] . NL . $delete_link_code . NL . ($crypted ? $option : 'O') .
            NL . $a['user_name']
    );
    fclose($handle);
    $hash_link = substr(base_16_to_64(md5_file($link_tmp_name)), 0, $link_name_length);
    $l = s2p("$hash_link");
    if (!@mkdir(VAR_LINKS . $l, 0755, true) ||
        !rename($link_tmp_name, VAR_LINKS . $l . $hash_link)) {
        return 'Error';
    }

    /* Clean async upload. */
    jirafeau_async_delete($ref);
    return $hash_link . NL . $delete_link_code . NL . urlencode($crypt_key);
}

function jirafeau_crypt_create_iv($base, $size)
{
    $iv = '';
    while (strlen($iv) < $size) {
        $iv = $iv . $base;
    }
    $iv = substr($iv, 0, $size);
    return $iv;
}

/* File verification */
function verification_check($file)
{
    // File content verification
    $file_size = filesize($file);
    if ($file_size === false || $file_size == 0) {
        return '';
    } else {
        return true;
    }
}

/**
 * Crypt file with openssl_encrypt
 * For 'AES-128-CBC' each block consist of 16 bytes.
 * @param string $fp_src Path to file that should be encrypted
 * @param string $key    The key used for the encryption
 * @param string $fp_dst   File name where the encryped file should be written to.
 */

function jirafeau_encrypt_file_openssl($fp_src, $key, $fp_dst, $cfg)
{
    // File content verification
    if (verification_check($fp_src) == true) {
        //Hash key
        $hash_key = md5($key);
        //vector initialization
        $iv = jirafeau_crypt_create_iv($hash_key, openssl_cipher_iv_length('aes-128-cbc'));
        $fpOut = fopen($fp_dst, 'c'); // 'c' Open the file for writing only. If the file does not exist, it will be created, if it exists, it is not truncated (unlike 'w')
        // Put the initialzation vector to the beginning of the file
        $fpIn = fopen($fp_src, 'rb');
        /* Crypt file */
        // feof: as long as the end of the file is not reached
        while (!feof($fpIn)) {
            $plaintext = fread($fpIn, $cfg['FILE_ENCRYPTION_BLOCKS']);
            $ciphertext = openssl_encrypt($plaintext, 'AES-128-CBC', $hash_key, OPENSSL_RAW_DATA, $iv);//OPENSSL_RAW_DATA: it will be understood as row data
            // Use the first 16 bytes of the ciphertext as the next initialization vector
            //Writing in the new encryption file
            fwrite($fpOut, $ciphertext);
        }
        //Close files
        fclose($fpIn);
        fclose($fpOut);
        return $key;
    } else {
       error_log("PHP extension: the file is empty");
    }
}

/**
 * openssl: Decrypt fileÒ
 *
 * @param string $link link informations of file
 * @param string $p letter separator
 * @param string $crypt_key crypted key
 * @param array $cfg application configuration, see config.original.php
 * @return string  Returns the file decrypted
 */
function jirafeau_decrypt_file_openssl($link, $p, $crypt_key, $cfg)
{
  /* File content verification */
  $file = VAR_FILES . $p . $link['hash'];
  if (verification_check($file) == true) {
      /* Hash key */
      $hash_key = md5($crypt_key);
      $fpIn = fopen($file, 'rb');
      /* Vector initialization */
      $iv = jirafeau_crypt_create_iv($hash_key, openssl_cipher_iv_length('aes-128-cbc'));
      // Get the initialzation vector from the beginning of the file
      while (!feof($fpIn)) {
          $ciphertext = fread($fpIn, $cfg['FILE_ENCRYPTION_BLOCKS'] ); // we have to read one block more for decrypting than for encrypting
          $plaintext = openssl_decrypt($ciphertext, 'AES-128-CBC', $hash_key, OPENSSL_RAW_DATA, $iv);
          print $plaintext;
          // Use the first 16 bytes of the ciphertext as the next initialization vector
      }
      fclose($fpIn);
  } else {
      error_log("PHP extension: the file is empty, won't decrypt in Jirafeau");
  }
}

/**
 * Crypt file and returns decrypt key.
 * @param $fp_src file path to the file to crypt.
 * @param $fp_dst file path to the file to write crypted file (could be the same).
 * @return decrypt key composed of the key and the iv separated by a point ('.')
 */
function jirafeau_encrypt_file($fp_src, $fp_dst)
{
    if(verification_check($fp_src) == true ) {
        /* Prepare module. */
        $m = mcrypt_module_open('rijndael-256', '', 'ofb', '');
        /* Generate key. */
        $crypt_key = jirafeau_gen_random(10);
        $hash_key = md5($crypt_key);
        $iv = jirafeau_crypt_create_iv($hash_key, mcrypt_enc_get_iv_size($m));
        /* Init module. */
        mcrypt_generic_init($m, $hash_key, $iv);
        /* Crypt file. */
        $r = fopen($fp_src, 'r');
        $w = fopen($fp_dst, 'c');
        while (!feof($r)) {
            $enc = mcrypt_generic($m, fread($r, 1024));
            if (fwrite($w, $enc) === false) {
                return '';
            }
        }
        fclose($r);
        fclose($w);
        /* Cleanup. */
        mcrypt_generic_deinit($m);
        mcrypt_module_close($m);
        return $crypt_key;
    } else {
        error_log("PHP extension: the file is empty");
    }
}

/**
 * Decrypt file.
 * @param $fp_src file path to the file to decrypt.
 * @param $fp_dst file path to the file to write decrypted file (could be the same).
 * @param $k string composed of the key and the iv separated by a point ('.')
 * @return key used to decrypt. a string of length 0 is returned if failed.
 */
function jirafeau_decrypt_file($fp_src, $fp_dst, $k)
{
    if (verification_check($fp_src) == true) {
        /* Init module */
        $m = mcrypt_module_open('rijndael-256', '', 'ofb', '');
        /* Extract key and iv. */
        $crypt_key = $k;
        $hash_key = md5($crypt_key);
        $iv = jirafeau_crypt_create_iv($hash_key, mcrypt_enc_get_iv_size($m));
        /* Decrypt file. */
        $r = fopen($fp_src, 'r');
        $w = fopen($fp_dst, 'c');
        while (!feof($r)) {
            $dec = mdecrypt_generic($m, fread($r, 1024));
            if (fwrite($w, $dec) === false) {
                return false;
            }
        }
        fclose($r);
        fclose($w);
        /* Cleanup. */
        mcrypt_generic_deinit($m);
        mcrypt_module_close($m);
        return true;
    } else {
        error_log("PHP extension: the file is empty");
    }
}

/**
 * mcrypt: Decrypt file
 *
 * @param string $link link informations of file
 * @param string $p letter separator
 * @param string $crypt_key crypted key
 * @param array $cfg application configuration, see config.original.php
 * @return string  Returns the file decrypted
 */
function jirafeau_decrypt_file_mcrypt($link, $p, $crypt_key, $cfg)
{
    /* File content verification */
    $file = VAR_FILES . $p . $link['hash'];
    if (verification_check($file) == true) {
        /* Hash key */
        $hash_key = md5($crypt_key);
        $fpIn = fopen($file, 'rb');
        /* Vector initialization */
        $iv = jirafeau_crypt_create_iv($hash_key, openssl_cipher_iv_length('aes-128-cbc'));
        // Get the initialzation vector from the beginning of the file
        while (!feof($fpIn)) {
            $ciphertext = fread($fpIn, $cfg['FILE_ENCRYPTION_BLOCKS'] ); // we have to read one block more for decrypting than for encrypting
            $plaintext = openssl_decrypt($ciphertext, 'AES-128-CBC', $hash_key, OPENSSL_RAW_DATA, $iv);
            print $plaintext;
            // Use the first 16 bytes of the ciphertext as the next initialization vector
        }
        fclose($fpIn);
    } else {
        error_log("PHP extension: the file is empty, won't decrypt in Jirafeau");
    }
}

/**
 * Check if Jirafeau is password protected for visitors.
 * @return true if Jirafeau is password protected, false otherwise.
 */
function jirafeau_has_upload_password($cfg)
{
    return count($cfg['upload_password']) > 0;
}

/**
 * Challenge password for a visitor.
 * @param $password password to be challenged
 * @return true if password is valid, false otherwise.
 */
function jirafeau_challenge_upload_password($cfg, $password)
{
    if (!jirafeau_has_upload_password($cfg)) {
        return false;
    }
    foreach ($cfg['upload_password'] as $p) {
        if ($password == $p) {
            return true;
        }
    }
    return false;
}

/**
 * Test if the given IP is whitelisted by the given list.
 *
 * @param $allowedIpList array of allowed IPs
 * @param $challengedIp IP to be challenged
 * @return true if IP is authorized, false otherwise.
 */
function jirafeau_challenge_ip($allowedIpList, $challengedIp)
{
    foreach ($allowedIpList as $i) {
        if ($i == $challengedIp) {
            return true;
        }
        // CIDR test for IPv4 only.
        if (strpos($i, '/') !== false) {
            list($subnet, $mask) = explode('/', $i);
            if ((ip2long($challengedIp) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Check if Jirafeau has a restriction on the IP address for uploading.
 * @return true if uploading is IP restricted, false otherwise.
 */
function jirafeau_upload_has_ip_restriction($cfg)
{
    return count($cfg['upload_ip']) > 0;
}

/**
 * Test if visitor's IP is authorized to upload at all.
 *
 * @param $cfg configuration
 * @param $challengedIp IP to be challenged
 * @return true if IP is authorized, false otherwise.
 */
function jirafeau_challenge_upload_ip($cfg, $challengedIp)
{
    // If no IP address have been listed, allow upload from any IP
    if (!jirafeau_upload_has_ip_restriction($cfg)) {
        return true;
    }
    return jirafeau_challenge_ip($cfg['upload_ip'], $challengedIp);
}

/**
 * Test if visitor's IP is authorized to upload without a password.
 *
 * @param $cfg configuration
 * @param $challengedIp IP to be challenged
 * @return true if IP is authorized, false otherwise.
 */
function jirafeau_challenge_upload_ip_without_password($cfg, $challengedIp)
{
    return jirafeau_challenge_ip($cfg['upload_ip_nopassword'], $challengedIp);
}

/**
 * Test if visitor's IP is authorized or password is supplied and authorized
 * @param $ip IP to be challenged
 * @param $password password to be challenged
 * @return true if access is valid, false otherwise.
 */
function jirafeau_challenge_upload($cfg, $ip, $password)
{
    return jirafeau_challenge_upload_ip_without_password($cfg, $ip) ||
            (!jirafeau_has_upload_password($cfg) && !jirafeau_upload_has_ip_restriction($cfg)) ||
            (jirafeau_challenge_upload_password($cfg, $password) && jirafeau_challenge_upload_ip($cfg, $ip));
}

/** Tell if we have some HTTP headers generated by a proxy */
function has_http_forwarded()
{
    return
        !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ||
        !empty($_SERVER['http_X_forwarded_for']);
}

/**
 * Generate IP list from HTTP headers generated by a proxy
 * @return  array of IP strings
 */
function get_ip_list_http_forwarded()
{
    $ip_list = array();
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $l = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        if ($l === false) {
            return array();
        }
        foreach ($l as $ip) {
            array_push($ip_list, preg_replace('/\s+/', '', $ip));
        }
    }
    if (!empty($_SERVER['http_X_forwarded_for'])) {
        $l = explode(',', $_SERVER['http_X_forwarded_for']);
        foreach ($l as $ip) {
            // Separate IP from port
            $ipa = explode(':', $ip);
            if ($ipa === false) {
                continue;
            }
            $ip = $ipa[0];
            array_push($ip_list, preg_replace('/\s+/', '', $ip));
        }
    }
    return $ip_list;
}

/**
 * Get the ip address of the client from REMOTE_ADDR
 * or from HTTP_X_FORWARDED_FOR if behind a proxy
 * @returns the client ip address
 */
function get_ip_address($cfg)
{
    $remote = $_SERVER['REMOTE_ADDR'];
    if (count($cfg['proxy_ip']) == 0 || !has_http_forwarded()) {
        return $remote;
    }

    $ip_list = get_ip_list_http_forwarded();
    if (count($ip_list) == 0) {
        return $remote;
    }

    foreach ($cfg['proxy_ip'] as $proxy_ip) {
        if ($remote != $proxy_ip) {
            continue;
        }
        // Take the last IP (the one which has been set by the defined proxy).
        return end($ip_list);
    }
    return $remote;
}

/**
 * Convert hexadecimal string to base64
 */
function hex_to_base64($hex)
{
    $b = '';
    foreach (str_split($hex, 2) as $pair) {
        $b .= chr(hexdec($pair));
    }
    return base64_encode($b);
}

/**
 * Replace markers in templates.
 *
 * Available markers have the scheme "###MARKERNAME###".
 *
 * @param $content string Template text with markers
 * @param $htmllinebreaks boolean Convert linebreaks to BR-Tags
 * @return Template with replaced markers
 */
function jirafeau_replace_markers($content, $htmllinebreaks = false)
{
    $patterns = array(
        '/###ORGANISATION###/',
        '/###CONTACTPERSON###/',
        '/###WEBROOT###/'
    );
    $replacements = array(
        $GLOBALS['cfg']['organisation'],
        $GLOBALS['cfg']['contactperson'],
        $GLOBALS['cfg']['web_root']
    );
    $content = preg_replace($patterns, $replacements, $content);

    if (true === $htmllinebreaks) {
        $content = nl2br($content);
    }

    return $content;
}

function jirafeau_escape($string)
{
    return htmlspecialchars($string, ENT_QUOTES);
}

function jirafeau_user_session_start()
{
    jirafeau_session_start('user');
}

function jirafeau_admin_session_start()
{
    jirafeau_session_start('admin');
}

function jirafeau_session_start($type)
{
    $_SESSION[jirafeau_csrf_auth_name($type)] = true;
    $_SESSION[jirafeau_csrf_field_name($type)] = md5(uniqid(mt_rand(), true));
}

function jirafeau_user_session_end()
{
    $_SESSION = array();
    session_destroy();
}

function jirafeau_admin_session_end()
{
    $_SESSION = array();
    session_destroy();
}

function jirafeau_user_session_logged()
{
    return jirafeau_session_logged('user');
}

function jirafeau_admin_session_logged()
{
    return jirafeau_session_logged('admin');
}

function jirafeau_session_logged($type)
{
    $type_auth = jirafeau_csrf_auth_name($type);
    $type_csrf = jirafeau_csrf_field_name($type);
    return isset($_SESSION[$type_auth]) &&
           isset($_SESSION[$type_csrf]) &&
           isset($_POST[$type_csrf]) &&
           $_SESSION[$type_auth] === true &&
           $_SESSION[$type_csrf] === $_POST[$type_csrf];
}

function jirafeau_csrf_field_name($type)
{
    return $type . '_csrf';
}

function jirafeau_csrf_auth_name($type)
{
    return $type . '_auth';
}

function jirafeau_user_csrf_field()
{
    return jirafeau_csrf_field('user');
}

function jirafeau_admin_csrf_field()
{
    return jirafeau_csrf_field('admin');
}

function jirafeau_csrf_field($type)
{
    $type_csrf = jirafeau_csrf_field_name($type);
    return "<input type='hidden' name='" . $type_csrf . "' value='". $_SESSION[$type_csrf] . "'/>";
}

function jirafeau_dir_size($dir)
{
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $entry) {
        $size += is_file($entry) ? filesize($entry) : jirafeau_dir_size($entry);
    }
    return $size;
}

function jirafeau_export_cfg($cfg)
{
    $content = '<?php' . NL;
    $content .= '/* This file was generated by the install process. ' .
               'You can edit it. Please see config.original.php to understand the ' .
               'configuration items. */' . NL;
    $content .= '$cfg = ' . var_export($cfg, true) . ';';

    $fileWrite = file_put_contents(JIRAFEAU_CFG, $content);

    if (false === $fileWrite) {
        jirafeau_fatal_error(t('Can not write local configuration file'));
    }
}

function jirafeau_mkdir($path)
{
    return !(!file_exists($path) && !@mkdir($path, 0755));
}

/**
 * Returns true whether the path is writable or we manage to make it
 * so, which essentially is the same thing.
 * @param $path is the file or directory to be tested.
 * @return true if $path is writable.
 */
function jirafeau_is_writable($path)
{
    /* "@" gets rid of error messages. */
    return is_writable($path) || @chmod($path, 0777);
}

function jirafeau_check_var_dir($path)
{
    $mkdir_str1 = t('CANNOT_CREATE_DIR') . ':';
    $mkdir_str2 = t('MANUAL_CREATE');
    $write_str1 = t('DIR_NOT_W') . ':';
    $write_str2 = t('You should give the write permission to the web server on ' .
                    'this directory.');
    $solution_str = t('HERE_SOLUTION') . ':';

    if (!jirafeau_mkdir($path) || !jirafeau_is_writable($path)) {
        return array('has_error' => true,
                      'why' => $mkdir_str1 . '<br /><code>' .
                               $path . '</code><br />' . $solution_str .
                               '<br />' . $mkdir_str2);
    }

    foreach (array('files', 'links', 'async') as $subdir) {
        $subpath = $path.$subdir;

        if (!jirafeau_mkdir($subpath) || !jirafeau_is_writable($subpath)) {
            return array('has_error' => true,
                          'why' => $mkdir_str1 . '<br /><code>' .
                                   $subpath . '</code><br />' . $solution_str .
                                   '<br />' . $mkdir_str2);
        }
    }

    return array('has_error' => false, 'why' => '');
}

function jirafeau_add_ending_slash($path)
{
    return $path . ((substr($path, -1) == '/') ? '' : '/');
}

/**
 * Function allowing the sending of mail from a form
 * see more : https://www.php.net/manual/fr/function.mail.php
 * @param $transmitter: the transmitter of the email
 * @param $recipient: the recipient of the email
 * @param $message is the message of the email
 * @return true if the mail send.
 */
function jirafeau_send_mail($transmitter, $recipients, $message, $link, $email_subject, $filename, $expireDate, $password, $cfg)
{
    $transmitter = filter_var($transmitter, FILTER_SANITIZE_EMAIL);
    $link = filter_var($link, FILTER_SANITIZE_URL);
    $message = filter_var($message, FILTER_SANITIZE_STRING);
    $email_subject = filter_var($email_subject, FILTER_SANITIZE_STRING);
    $filename = filter_var($filename, FILTER_SANITIZE_STRING);
    $recipients = explode(",", $recipients);
    $arrayRecipients = [];
    foreach ($recipients as $element) {
        $element = filter_var($element, FILTER_SANITIZE_EMAIL);
        $element = filter_var($element, FILTER_VALIDATE_EMAIL);
        if ($element == false && !empty($element)) {
            echo t('INCORRECT_RECIPIENTS');
            break;
        } else if (empty($element)) {
            echo t('EMPTY_RECIPIENTS');
        }
        array_push($arrayRecipients, $element);
    }
    $arrayRecipients = implode(",", $arrayRecipients);
    if (($transmitter && $link && $message && $email_subject && $filename) == true) {
        if (filter_var($transmitter, FILTER_VALIDATE_EMAIL)) {
                $message = '<html>
                <style>
                h1 {
                    color: #663d1c;
                }
                body {
                    justify-content: center;
                    display: flex;
                }
                strong {
                    color: #663d1c;
                }
                #expireFile {
                    font-size: 14px;
                    margin-top: 0px;
                }
                h3 {
                    margin: 0px;
                }
                .block {
                    margin-top: 20px;
                    margin-bottom: 20px;
                }
                #text {
                    margin: 30px;
                }
                button {
                    text-transform: uppercase;
                    outline: 2px;
                    background: #663d1c;
                    width: 35%;
                    padding: 15px;
                    color: #FFF;
                    font-size: 14px;
                    cursor: pointer;
                    border-radius: 20px;
                }
                #mail {
                    background-color: #f4f4f4;
                }
            </style>
            <head>
                <title>Jyrafon</title>
            </head>
            <body>
                <div id="mail">
                    <h1>Jyrafon</h1>
                    <div class="block">
                        <h2>
                            <strong>' . $transmitter . '</strong> sent you a file
                        </h2>
                        <p id="expireFile">Expires on ' . $expireDate . '</p>
                    </div>
                    <div id="text">
                        <p>' . $message .'</p>
                    </div>
                    <button href=' . $link .'>Get your file</button>
                    <div class="block">
                        <h3>Dowload link:</h3>
                        <p>' . $link . '</p>
                        <h3>Files: ' . $filename .'</h3>
                        <h4>Password:' . $password . '</h4>
                    </div>
                </div>
            </body>
            </html>';
            if ($arrayRecipients == "") {
                echo t('EMPTY_RECIPIENTS');
            } else {
                $headers[] = 'Bcc:'. $arrayRecipients;
                if ($cfg['noreplyTransmitter'] = "") {
                    $headers[] = 'From:' . $transmitter;
                } else {
                    $headers[] = 'From:' . $cfg['noreplyTransmitter'];
                }
                $headers[] = "Reply-to:" . $transmitter;
                $headers[] = "Importance: Normal";
                $headers[] = "MIME-Version: 1.0";
                $headers[] = 'Content-Type: text/html; charset="utf-8"';
                $headers[] = 'Content-Tranfert-Encoding: 8bit';
                return mail(null, $email_subject, $message, implode("\r\n", $headers));
            }
        } else {
            echo t('VALID_INPUTS');
        }
    } else {
        echo t('INCORRECT_INFO');
    }
}
