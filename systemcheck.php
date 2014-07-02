<?php

/**
 * kitFramework SystemCheck
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

class SystemCheck
{
    protected static $detected_PHP_VERSION = null;
    protected static $required_PHP_VERSION = null;

    protected static $CMS_TYPE = null;
    protected static $CMS_VERSION = null;

    protected static $detected_MYSQL_VERSION = null;
    protected static $required_MYSQL_VERSION = null;

    protected static $detected_CURL = null;
    protected static $required_CURL = null;

    protected static $detected_ZIPArchive = null;
    protected static $required_ZIPArchive = null;

    protected static $cms_config_path = null;

    protected static $innoDB_available = null;
    protected static $required_innoDB = null;

    protected static $PDO_MySQL_enabled = null;
    protected static $required_PDO_MySQL = null;

    protected static $magic_quotes_gpc = null;
    protected static $required_magic_quotes_gpc = null;

    protected static $supported_cms_types = array(
        'WebsiteBaker',
        'LEPTON CMS',
        'BlackCat CMS'
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        date_default_timezone_set('Europe/Berlin');
        self::setCMSpath();
    }

    /**
     * Set the path of the parent CMS
     */
    protected static function setCMSpath()
    {
        //  we assume that the SystemCheck is placed at / or at /kit2
        if (file_exists(realpath(dirname(__FILE__).'/../config.php'))) {
            self::$cms_config_path = realpath(dirname(__FILE__).'/../config.php');
        }
        elseif (file_exists(realpath(dirname(__FILE__).'/config.php'))) {
            self::$cms_config_path = realpath(dirname(__FILE__).'/config.php');
        }
        else {
            self::$cms_config_path = null;
        }
    }

    /**
     * Set the required PHP version
     *
     * @param string $php_version i.e. '5.3.2'
     */
    public function setRequriredPHPVersion($php_version)
    {
        self::$required_PHP_VERSION = $php_version;
    }

    /**
     * Set the required Magic Quotes settings
     *
     * @param unknown $magic_quotes
     */
    public function setRequiredMagicQuotesGPC($magic_quotes)
    {
        self::$required_magic_quotes_gpc = $magic_quotes;
    }

    /**
     * Set the required MySQL version
     *
     * @param string $mysql_version i.e. '5.0.0'
     */
    public function setRequiredMySQLVersion($mysql_version)
    {
        self::$required_MYSQL_VERSION = $mysql_version;
    }

    /**
     * Determine wether PDO is needed or not
     *
     * @param boolean $required
     */
    public function setRequiredPDOmySQL($required)
    {
        self::$required_PDO_MySQL = (bool) $required;
    }

    /**
     * Determine wether InnoDB is needed or not
     *
     * @param boolean $required
     */
    public function setRequiredInnoDB($required)
    {
        self::$required_innoDB = (bool) $required;
    }

    /**
     * Determine wether cURL is needed or not
     *
     * @param boolean $required
     */
    public function setRequiredCURL($required)
    {
        self::$required_CURL = (bool) $required;
    }

    /**
     * Determine wether the ZIPArchive is needed or not
     *
     * @param boolean $required
     */
    public function setRequriredZIPArchive($required)
    {
        self::$required_ZIPArchive = (bool) $required;
    }

    /**
     * Return the operating system: WINDOWS or LINUX
     *
     * @return string
     */
    protected function getOperatingSystem()
    {
        return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'WINDOWS' : 'LINUX';
    }

    /**
     * Try to get informations about the parent CMS
     *
     * @return Ambigous <string, multitype:NULL >
     */
    protected function getCMSinformation()
    {
        $result = array(
            'Type' => '- no information available -',
            'Version' => '- no information available -',
            'Supported' => 'No',
            'css' => 'fail'
            );
        if (file_exists(self::$cms_config_path)) {
            include_once self::$cms_config_path;
            if (defined('DB_HOST')) {
                // establish MySQL connection
                if ((false !== ($db_handle = @mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD))) &&
                    @mysql_select_db(DB_NAME, $db_handle)) {
                    // select the CMS settings
                    $SQL = "SELECT * FROM `".TABLE_PREFIX."settings`";
                    if (false !== ($query = @mysql_query($SQL))) {
                        // reset CMS type and version
                        self::$CMS_TYPE = null;
                        self::$CMS_VERSION = null;
                        while (false !== ($row = mysql_fetch_assoc($query))) {
                            if ($row['name'] == 'wb_version') {
                                self::$CMS_TYPE = 'WebsiteBaker';
                                self::$CMS_VERSION = $row['value'];
                                break;
                            }
                            if ($row['name'] == 'lepton_version') {
                                self::$CMS_TYPE = 'LEPTON CMS';
                                self::$CMS_VERSION = $row['value'];
                                break;
                            }
                            if ($row['name'] == 'cat_version') {
                                self::$CMS_TYPE = 'BlackCat CMS';
                                self::$CMS_VERSION = $row['value'];
                                break;
                            }
                        }
                        @mysql_free_result($query);
                        if (!is_null(self::$CMS_TYPE)) {
                            $result = array(
                                'Type' => self::$CMS_TYPE,
                                'Version' => self::$CMS_VERSION,
                                'Supported' => $this->isCMSsupported() ? 'Yes' : 'No',
                                'css' => $this->isCMSsupported() ? 'checked' : 'fail'
                            );
                        }
                    }
                    // get information about InnoDB
                    $SQL = "SELECT SUPPORT FROM INFORMATION_SCHEMA.ENGINES WHERE ENGINE = 'InnoDB'";
                    if (false !== ($query = @mysql_query($SQL))) {
                        if (false !== ($row = mysql_fetch_assoc($query))) {
                            if (isset($row['SUPPORT']) && ($row['SUPPORT'] != 'NO')) {
                                self::$innoDB_available = true;
                            }
                            else {
                                self::$innoDB_available = false;
                            }
                        }
                    }
                    // close the db handle
                    @mysql_close($db_handle);
                }
            }
        }
        return $result;
    }

    /**
     * Check if the CMS is supported
     *
     * @return boolean
     */
    protected function isCMSsupported()
    {
        if (in_array(self::$CMS_TYPE, self::$supported_cms_types)) {
            if (self::$CMS_TYPE == 'WebsiteBaker') {
                // support WB 2.8.x and above
                if (version_compare(self::$CMS_VERSION, '2.8.0', '>=')) {
                    return true;
                }
            }
            else {
                // no restrictions for other CMS versions
                return true;
            }
        }
        return false;
    }


    /**
     * Get InnoDB information
     *
     * @return array
     */
    protected function getInnoDBinformation()
    {
        if (is_null(self::$innoDB_available)) {
            return array(
                'required' => self::$required_innoDB ? 'Yes' : 'No',
                'available' => '- no information available -',
                'css' => 'fail'
            );
        }
        else {
            return array(
                'required' => self::$required_innoDB ? 'Yes' : 'No',
                'available' => self::$innoDB_available ? 'Yes' : 'No',
                'css' => (self::$required_innoDB && !self::$innoDB_available) ? 'fail' : 'checked'
            );
        }
    }

    /**
     * Return a valid version string for the MySQL client version,
     * using mysqli_get_client_version()
     *
     * @return string
     */
    protected function getMySQLversion()
    {
        // for version 4.1.6 return 40106;
        $mysqlVersion =  mysqli_get_client_version();
        //create mysql version string to check it
        $mainVersion = (int)($mysqlVersion/10000);
        $a = $mysqlVersion - ($mainVersion*10000);
        $minorVersion = (int)($a/100);
        $subVersion = $a - ($minorVersion*100);
        return $mainVersion.'.'.$minorVersion.'.'.$subVersion;
    }

    /**
     * Return an array with information about MySQL
     *
     * @return multitype:mixed NULL
     */
    protected function getMySQLinformation()
    {
        self::$detected_MYSQL_VERSION = $this->getMySQLversion();
        if (is_null(self::$required_MYSQL_VERSION)) {
            self::$required_MYSQL_VERSION = self::$detected_MYSQL_VERSION;
        }
        return array(
            'installed' => self::$detected_MYSQL_VERSION,
            'required' => self::$required_MYSQL_VERSION,
            'checked' => (int) version_compare(self::$detected_MYSQL_VERSION, self::$required_MYSQL_VERSION, '>='),
            'css' => version_compare(self::$detected_MYSQL_VERSION, self::$required_MYSQL_VERSION, '>=') ? 'checked' : 'fail'
            );
    }

    /**
     * Return an array with information about PHP
     *
     * @return multitype:number NULL
     */
    protected function getPHPinformation()
    {
        if (is_null(self::$required_PHP_VERSION)) {
            self::$required_PHP_VERSION = PHP_VERSION;
        }
        self::$detected_PHP_VERSION = PHP_VERSION;
        return array(
            'installed' => self::$detected_PHP_VERSION,
            'required' => self::$required_PHP_VERSION,
            'checked' => (int) version_compare(self::$detected_PHP_VERSION, self::$required_PHP_VERSION, '>='),
            'css' => version_compare(self::$detected_PHP_VERSION, self::$required_PHP_VERSION, '>=') ? 'checked' : 'fail'
        );
    }

    /**
     * Return an array with information about cURL
     *
     * @return array
     */
    protected function isCURLinstalled()
    {
        self::$detected_CURL = function_exists('curl_init');
        if (is_null(self::$required_CURL)) {
            self::$required_CURL = self::$detected_CURL;
        }
        return array(
            'installed' => (int) self::$detected_CURL,
            'required' => (int) self::$required_CURL,
            'checked' => (self::$required_CURL && !self::$detected_CURL) ? 0 : 1,
            'css' => (self::$required_CURL && !self::$detected_CURL) ? 'fail' : 'checked'
            );
    }

    /**
     * Return an array with information about PDO_MYSQL
     *
     * @return array
     */
    protected function isPDOmySQLenabled()
    {
        self::$PDO_MySQL_enabled = extension_loaded('pdo_mysql');
        if (is_null(self::$required_PDO_MySQL)) {
            self::$required_PDO_MySQL = self::$PDO_MySQL_enabled;
        }
        return array(
            'installed' => (int) self::$PDO_MySQL_enabled,
            'required' => (int) self::$required_PDO_MySQL,
            'checked' => (self::$required_PDO_MySQL && !self::$PDO_MySQL_enabled) ? 0 : 1,
            'css' => (self::$required_PDO_MySQL && !self::$PDO_MySQL_enabled) ? 'fail' : 'checked'
        );
    }

    /**
     * Return an array with information about the Magic Quotes settings
     *
     * @return array
     */
    protected function isMagicQuotesGPC()
    {
        self::$magic_quotes_gpc = filter_var(ini_get('magic_quotes_gpc'), FILTER_VALIDATE_BOOLEAN);

        if (is_null(self::$required_magic_quotes_gpc)) {
            self::$required_magic_quotes_gpc = self::$magic_quotes_gpc;
        }
        return array(
            'installed' => self::$magic_quotes_gpc ? 'on' : 'off',
            'required' => self::$required_magic_quotes_gpc ? 'on' : 'off',
            'checked' => (self::$required_magic_quotes_gpc && self::$magic_quotes_gpc) ? 0 : 1,
            'css' => (self::$required_magic_quotes_gpc === self::$magic_quotes_gpc) ? 'checked' : 'fail'
        );
    }

    /**
     * Return an array with information about the ZipArchive
     *
     * @return multitype:number
     */
    protected function isZIParchiveInstalled()
    {
        self::$detected_ZIPArchive = class_exists('ZipArchive');
        if (is_null(self::$required_ZIPArchive)) {
            self::$required_ZIPArchive = self::$detected_ZIPArchive;
        }
        return array(
            'installed' => (int) self::$detected_ZIPArchive,
            'required' => (int) self::$required_ZIPArchive,
            'checked' => (self::$required_ZIPArchive && !self::$detected_ZIPArchive) ? 0 : 1,
            'css' => (self::$required_ZIPArchive && !self::$detected_ZIPArchive) ? 'fail' : 'checked'
        );
    }

    protected static function promptResult($result)
    {
        $curl_required = $result['cURL']['required'] ? 'Yes' : 'No';
        $curl_installed = $result['cURL']['installed'] ? 'Yes' : 'No';
        $zip_required = $result['ZIPArchive']['required'] ? 'Yes' : 'No';
        $zip_installed = $result['ZIPArchive']['installed'] ? 'Yes' : 'No';
        $pdo_mysql_required = $result['PDOmySQL']['required'] ? 'Yes' : 'No';
        $pdo_mysql_enabled = $result['PDOmySQL']['installed'] ? 'Yes' : 'No';
        echo <<<EOD
<!DOCTYPE html>
<html lang=en>
    <head>
        <meta charset=utf-8>
        <title>SystemCheck for the kitFramework</title>
        <style type="text/css" media="screen">
            body {
                margin: 0;
                padding: 0 0 0 50px;
                font-family: "Lucida Sans Unicode", "Lucida Grande", sans-serif;
                font-size: 13px;
                color: #363636;
                background-color: #fff;
            }

            a {
                text-decoration: none;
            }
            a:link,
            a:visited {
                color: #da251d;
                background-color: transparent;
                text-decoration: none;
            }
            a:hover,
            a:active {
                color: #da251d;
                background-color: transparent;
                text-decoration: underline;
            }
            fieldset {
                clear: both;
                margin: 10px 0;
                padding: 10px;
                width: 600px;
            }
            legend {
                font-size: 11px;
            }
            .label {
                clear: both;
                float: left;
                width: 180px;
                margin: 0;
                padding: 0;
            }
            .value {
                float: left;
                width: 300px;
                margin: 0;
                padding: 0;
            }
            .checked {
                color: #006400;
                background-color: transparent;
                font-weight: normal;
            }
            .fail {
                color: #da251d;
                background-color: transparent;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <h1>kitFramework SystemCheck</h2>
        <p>&copy 2014 <a href="https://phpmanufaktur.de">phpManufaktur</a> by <a href="mailto:ralf.hertsch@phpmanufaktur.de">Ralf Hertsch</a></p>
        <fieldset>
            <legend>CMS</legend>
            <div class="label">CMS Type</div>
            <div class="value">{$result['CMS']['Type']}</div>
            <div class="label">CMS Version</div>
            <div class="value">{$result['CMS']['Version']}</div>
            <div class="label">kitFramework compatible</div>
            <div class="value {$result['CMS']['css']}">{$result['CMS']['Supported']}</div>
        </fieldset>
        <fieldset>
            <legend>Operating System</legend>
            <div class="label">Operating System</div>
            <div class="value">{$result['OPERATING_SYSTEM']}</div>
        </fieldset>
        <fieldset>
            <legend>PHP Version</legend>
            <div class="label">Required</div>
            <div class="value">{$result['PHP_VERSION']['required']}</div>
            <div class="label">Installed</div>
            <div class="value {$result['PHP_VERSION']['css']}">{$result['PHP_VERSION']['installed']}</div>
        </fieldset>
        <fieldset>
            <legend>Magic Quotes</legend>
            <div class="label">Required</div>
            <div class="value">{$result['MagicQuotesGPC']['required']}</div>
            <div class="label">php.ini setting</div>
            <div class="value {$result['MagicQuotesGPC']['css']}">{$result['MagicQuotesGPC']['installed']}</div>
        </fieldset>
        <fieldset>
            <legend>MySQL Version</legend>
            <div class="label">Required</div>
            <div class="value">{$result['MySQL']['required']}</div>
            <div class="label">Installed</div>
            <div class="value {$result['MySQL']['css']}">{$result['MySQL']['installed']}</div>
        </fieldset>
        <fieldset>
            <legend>PDO MySQL Extension</legend>
            <div class="label">Required</div>
            <div class="value">{$pdo_mysql_required}</div>
            <div class="label">Available</div>
            <div class="value {$result['PDOmySQL']['css']}">{$pdo_mysql_enabled}</div>
        </fieldset>
        <fieldset>
            <legend>MySQL InnoDB Engine</legend>
            <div class="label">Required</div>
            <div class="value">{$result['InnoDB']['required']}</div>
            <div class="label">Available</div>
            <div class="value {$result['InnoDB']['css']}">{$result['InnoDB']['available']}</div>
        </fieldset>
        <fieldset>
            <legend>cURL</legend>
            <div class="label">Required</div>
            <div class="value">{$curl_required}</div>
            <div class="label">Installed</div>
            <div class="value {$result['cURL']['css']}">{$curl_installed}</div>
        </fieldset>
        <fieldset>
            <legend>ZipArchive</legend>
            <div class="label">Required</div>
            <div class="value">{$zip_required}</div>
            <div class="label">Installed</div>
            <div class="value {$result['ZIPArchive']['css']}">{$zip_installed}</div>
        </fieldset>
        <div class="info">
            <p>To get a <a href="?action=phpinfo">detailed PHP information</a> you can use the parameter <code>systemcheck.php?action=phpinfo</code>.</p>
            <p>Visit the <a href="https://kit2.phpmanufaktur.de">kitFramework Project</a> and the <a href="https://github.com/phpManufaktur/kitFramework/wiki">kitFramework WIKI</a> to get more information.</p>
            <p>Please feel free to contact the <a href="https://support.phpmanufaktur.de">phpManufaktur Support Group</a> to receive assistance.</p>

        </div>
    </body>
</html>
EOD;
    }

    public function exec($prompt_result=true)
    {
        $result = array(
            'CMS' => $this->getCMSinformation(),
            'OPERATING_SYSTEM' => $this->getOperatingSystem(),
            'PHP_VERSION' => $this->getPHPinformation(),
            'MySQL' => $this->getMySQLinformation(),
            'InnoDB' => $this->getInnoDBinformation(),
            'cURL' => $this->isCURLinstalled(),
            'ZIPArchive' => $this->isZIParchiveInstalled(),
            'PDOmySQL' => $this->isPDOmySQLenabled(),
            'MagicQuotesGPC' => $this->isMagicQuotesGPC()
        );
        return ($prompt_result) ? self::promptResult($result) : $result;
    }

}


if (isset($_GET['action']) && ($_GET['action'] === strtolower('phpinfo'))) {
    phpinfo();
    exit();
}



// check the system for the kitFramework
$info = new SystemCheck();
$info->setRequriredPHPVersion('5.3.3');
$info->setRequiredMySQLVersion('5.0.3');
$info->setRequiredCURL(true);
$info->setRequriredZIPArchive(true);
$info->setRequiredInnoDB(true);
$info->setRequiredPDOmySQL(true);
$info->setRequiredMagicQuotesGPC(false);
$info->exec();
