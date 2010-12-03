<?php
# By Michael Wetmore.
# VERSIONS:
# $version = '1.0.0 - 23 November 2010';
# $version = '1.1.0 - 28 November 2010';
/*
    Revised command line to make sense.  Now 'minute' etc. is a parameter
        not an option.
    Added option to log run data to a file.
    Added options for all TED and MySQL parameters.
*/
$version = '1.1.1 - 3 December 2010';
/*
    Cleaned up arithmetic to determine number of records to retrieve 
	    by dividing after adding last_update and max_offset - was before.
	Added more debug output.
	No more '1 records' in non-quiet output.
*/
# $version = 'x.x.x - 31 December 2050';
/*
    Option to log to a database table.
    Create database tables if not found.
    Options to delete minute and/or second history after a defined time.
*/
/************************************************************************
  Program Settings -    ## are changed by this script to its requirements.
                        #* are defaults that may be changed with command
                            line options.
*/
$TED_HOSTNAME = 'ted5000';  #* The hostname of the TED gateway device
$TED_PORT     = 80;         #* The port number of the TED gateway device
$TED_USERNAME = '';         #* The authentication username
$TED_PASSWORD = '';         #* The authentication password
$TED_SSL      = FALSE;      #* Enable/Disable SSL (TRUE or FALSE)
$TED_API      = 'minutehistory'; ## livedata (always returns XML)
//                          | secondhistory | minutehistory
//                          | hour(ly)history | month(ly)history
$TED_MTU      = 0;          ## The MTU number for data (0-3)
$TED_TYPE     = 'all';      ## power | cost | voltage | all
$TED_FORMAT   = 'raw';      ## raw | xml | csv
$TED_DB_HOST  = 'localhost';#* The database server
$TED_DB_NAME  = 'ted';      #* Database name
$TED_DB_USER  = 'root';     #* Username to access the database
$TED_DB_PASS  = 'password'; #* Password to access the database
$TED_DB_TABLE = 'combined_history'; #* the table to use
/*
This function is used to convert the SimpleXMLElement
returned by a fetch of livedata into an array.
*/
function simplexml2array($xml) {
    if (@get_class($xml) == 'SimpleXMLElement') {
        $attributes = $xml->attributes();
        foreach($attributes as $k => $v) {
            if ($v) $a[$k] = (string)$v;
        }
        $x = $xml;
        $xml = get_object_vars($xml);
    }
    if (is_array($xml)) {
        if (count($xml) == 0) return (string)$x; // for CDATA
        foreach($xml as $key => $value) {
            $r[$key] = simplexml2array($value);
        }
        if (isset($a)) $r['@'] = $a;    // Attributes
        return $r;
    }
return (string)$xml;
}
/*
Required stuff and where to get it
*/
require_once 'TED_PHP.class.php';
                # http://www.garrettbartley.com/TED_PHP
require_once 'Console/Getoptplus.php';
                # http://pear.php.net/package/Console_GetoptPlus
$parameters = "<s or second | m or minute "
            ."| h or hour | d or day | o or month>";
try {
    $config = array(
        'header'  => array('', $argv[0].' Version: '.$version, ''),
        'usage'   => array('[options to overide defaults]'
                            .' <one required parameter>'),
        'options' => array(
            array('long' => 'help', 'type' => 'noarg', 'short'
                => 'h', 'desc' => array(
                "This help.",
                ""
                )
            ),
            array('long' => 'quiet', 'type' => 'noarg', 'short'
                => 'q', 'desc' => array(
                "Suppress progress messages.",
                "\tThe default is FALSE."
                )
            ),
            array('long' => 'debug', 'type' => 'noarg', 'short'
                => 'd', 'desc' => array(
                "Show values passed to TED",
                "\tThe default is FALSE."
                )
            ),
            array('long' => 'log', 'type' => 'mandatory', 'short'
                => 'l', 'desc' => array(
                'arg',
                "Log activity to the file named in the argument.",
                "\tThe default is no log."
                )
            ),
            array('long' => 'dbhost', 'type' => 'mandatory',
                'desc' => array(
                'arg',
                "The database server's IP or name.",
                "\tThe default is '".$TED_DB_HOST."'."
                )
            ),
            array('long' => 'dbname', 'type' => 'mandatory',
                'desc' => array(
                'arg',
                "The database name on the server.",
                "\tThe default is '".$TED_DB_NAME."'."
                )
            ),
            array('long' => 'dbtable', 'type' => 'mandatory',
                'desc' => array(
                'arg',
                "The table in the database.",
                "\tThe default is '".$TED_DB_TABLE."'."
                )
            ),
            array('long' => 'dbuser', 'type' => 'mandatory',
                'desc' => array(
                'arg',
                "The user ID to access the database.",
                "\tThe default is '".$TED_DB_USER."'."
                )
            ),
            array('long' => 'dbpass', 'type' => 'mandatory',
                'desc' => array(
                'arg',
                "The database user's password.",
                "\tThe default is '".$TED_DB_PASS."'."
                )
            ),
            array('long' => 'tdhost', 'type' => 'mandatory',
                'desc' => array(
                'arg',
                "TED's IP address or name.",
                "\tThe default is '".$TED_HOSTNAME."'."
                )
            ),
            array('long' => 'tdport', 'type' => 'mandatory',
                'desc' => array(
                'arg',
                "TED's webserver port.",
                "\tThe default is ".$TED_PORT.'.'
                )
            ),
            array('long' => 'tduser', 'type' => 'mandatory',
                'desc' => array(
                'arg',
                "User ID to log in to TED.",
                "\tThe default is '".$TED_USERNAME."'."
                )
            ),
            array('long' => 'tdpass', 'type' => 'mandatory',
                'desc' => array(
                'arg',
                "User ID to log in to TED.",
                "\tThe default is '".$TED_PASSWORD."'."
                )
            ),
            array('long' => 'tdssl', 'type' => 'noarg',
                'desc' => array(
                "Use SSL (https:) to access TED.",
                "\tThe default is FALSE."
                )
            )
        ),
        'parameters' => array('',
            $parameters.'.',
            "Selects which TED history to pass to the database."
        ),
    );
    $options = Console_Getoptplus::getoptplus(
        $config,
        'short2long',
        TRUE,
        'strict',
        TRUE);
}
catch(Console_GetoptPlus_Exception $e) {
    echo $e->getMessage().PHP_EOL."\tTry ".$argv[0]." --help".PHP_EOL;
    exit;
}
/*
Process command line options
*/
$quiet = FALSE; $debug = FALSE;
if (count($options[0]) > 0) {
    foreach($options[0] as $option => $v) {
        if ($option == 'quiet')   $quiet        = TRUE;
        if ($option == 'debug')   $debug        = TRUE;
        if ($option == 'log')     $log          = $v;
        if ($option == 'dbhost')  $TED_DB_HOST  = $v;
        if ($option == 'dbname')  $TED_DB_NAME  = $v;
        if ($option == 'dbuser')  $TED_DB_USER  = $v;
        if ($option == 'dbpass')  $TED_DB_PASS  = $v;
        if ($option == 'dbtable') $TED_DB_TABLE = $v;
        if ($option == 'tdhost')  $TED_HOSTNAME = $v;
        if ($option == 'tduser')  $TED_USERNAME = $v;
        if ($option == 'tdpass')  $TED_PASSWORD = $v;
        if ($option == 'tdport')  $TED_PORT     = $v;
        if ($option == 'tdssl')   $TED_SSL      = TRUE;
    }
}
/*
open or create the log file
*/
if (isset($log)) {
    if (strpos($log, '\\')) $log = addslashes($log);
    if (! is_dir(dirname($log)))
        mkdir(dirname($log), 0, TRUE);
    if (! ($fp = fopen($log, "a")))
        die("Can't create or open ".$log.PHP_EOL);
}
/*
set values depending on the parameter
exit with an error message if it is incorrect
*/
if (count($options[1]) > 0) {
    switch (strtolower(trim($options[1][0]))) {
        case 's':
        case 'second':
            $cost_divisor = 100 * 60 * 60;
            $divisor = 1;
            $api = 'secondhistory';
            $intervalword = ' second';
            $interval = 0;
            $hist = 's';
            break;
        case 'm':
        case 'minute':
            $cost_divisor = 100 * 60;
            $divisor = 60;
            $api = 'minutehistory';
            $intervalword = ' minute';
            $interval = 1;
            $hist = 'm';
            break;
        case 'h':
        case 'hour':
            $cost_divisor = 100;
            $divisor = 60 * 60;
            $api = 'hourhistory';
            $intervalword = ' hour';
            $interval = 2;
            $hist = 'h';
            break;
        case 'd':
        case 'day':
            $cost_divisor = 100;
            $divisor = 60 * 60 * 24;
            $api = 'dayhistory';
            $intervalword = ' day';
            $interval = 3;
            $hist = 'd';
            break;
        case 'o':
        case 'month':
            $cost_divisor = 100;
            $divisor = 60 * 60 * 24 * 30;
            $api = 'monthhistory';
            $intervalword = ' month';
            $interval = 4;
            $hist = 'o';
            break;
        default:
            echo "Invalid argument: "
                .strtolower($options[1][0]).PHP_EOL
                ."\t".$parameters.PHP_EOL
                ."\tTry ".$argv[0]." --help".PHP_EOL;
                exit;
            break;
    }
} else {
    echo "An argument is required: ".PHP_EOL
        ."\t".$parameters.PHP_EOL
        ."\tTry ".$argv[0]." --help".PHP_EOL;
    exit;
}
/*
Start time for progress messsges
*/
if (! $quiet) $start_ts = time();
/*
Set the century
*/
$century = substr(date('Y'), 0, 2);
/*
Connect to the server and the database
*/
$mysqli = new mysqli(
    $TED_DB_HOST,
    $TED_DB_USER,
    $TED_DB_PASS,
    $TED_DB_NAME);
if ($mysqli->connect_error)
    die('Connect Error ('.$mysqli->connect_errno.') '
        .$mysqli->connect_error);
/*
Instantiate the TED_PHP object
*/
$ted = new TED_PHP(
    $TED_HOSTNAME,
    $TED_PORT,
    $TED_USERNAME,
    $TED_PASSWORD,
    $TED_SSL,
    $TED_API,
    $TED_MTU,
    $TED_TYPE,
    $TED_FORMAT);
/*
find out how many MTUs are installed
*/
$ted->set_api('livedata');
$live = simplexml2array($ted->fetch());
for ($i = 1; $i < 5; $i++)
    $mtulist[$i] =
        $live['Voltage']['MTU'.$i]['VoltageNow'];
/*
now go and get the history data
*/
$ted->set_api($api);
$ted->set_format('raw');
foreach ($mtulist as $mtu => $volts) {
    if ($volts != 0) { // then the MTU is installed.
        /*
        Get the number of seconds since the last update,
        fall back on the time since TED-5000 introduction.
        The subquery in the WHERE ensures that both
        timestamps are from the same record.
        */
        $query = <<<EOQ
            SELECT
            UNIX_TIMESTAMP(NOW())
                - UNIX_TIMESTAMP(`ted_ts`) AS `last_update`,
            UNIX_TIMESTAMP(`ted_ts`)
                - UNIX_TIMESTAMP(`ins_ts`) AS 'max_offset'
            FROM `$TED_DB_TABLE`
            WHERE `ted_ts` =
            (SELECT MAX(`ted_ts`) FROM `$TED_DB_TABLE`
                WHERE `hist` = '$hist'
                AND `mtu` = $mtu)
            AND `hist` = '$hist'
            AND `mtu` = $mtu;
EOQ;
        $last_update = time() - strtotime("1 June 2009");
        $max_offset = 0;
        if ($result = $mysqli->query($query, MYSQLI_STORE_RESULT)) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $last_update = $row['last_update'];
                $max_offset  = $row['max_offset'];
				if ($debug) print_r($row);
            }
        }
        $result->close();
        $records_to_get = ceil(($last_update + $max_offset) / $divisor);
        if (! $quiet) {
            echo PHP_EOL."  * For MTU".$mtu.":".PHP_EOL
                ."\tLast update was "
                .number_format($last_update / $divisor, 2)
                .$intervalword.'s ago.'
                ."  Will retrieve "
                .number_format($records_to_get)
                .' record'.($records_to_get > 1 ? 's' : '')
				.' from TED.'.PHP_EOL;
        }
        $ted->set_mtu($mtu - 1);
        if ($debug) {
            $settings = array('host', 'port', 'username', 'password',
                        'ssl', 'api', 'mtu', 'type', 'format');
            foreach ($settings as $s => $v) {
                eval("echo substr('$settings[$s]', 0, 4)
                    .\":\t>\"
                    .\$ted->get_$settings[$s]()
                    .\"<\".PHP_EOL;"
                );
            }
        }
        $moments = $ted->fetch(1, $records_to_get, FALSE);
        if (! $quiet || isset($log)) {
            $a = 0;
            $inserted_rows = 0;
            $updated_rows = 0;
            $recs_found = count($moments);
        }
        /*
        Loop through the results and insert into the table
        */
		if ($debug) print_r($moments);
        foreach($moments as $moment) {
            $ted_ts =
                $century
                .substr("0".$moment['year'], -2, 2)
                .'-'.$moment['month'].'-';
            switch ($interval) {
                case 0: /* seconds */
                    $ted_ts .=
                    $moment['day'].' '
                    .$moment['hour'].':'
                    .$moment['minute'].':'
                    .$moment['second'];
                break;
                case 1: /* minutes */
                    $ted_ts .=
                    $moment['day'].' '
                    .$moment['hour'].':'
                    .$moment['minute'].':59';
                break;
                case 2: /* hours */
                    $ted_ts .=
                    $moment['day'].' '
                    .$moment['hour'].':59:59';
                break;
                case 3: /* days */
                    $ted_ts .=
                    $moment['day'].' 23:59:59';
                break;
                case 4: /* months */
                    $ted_ts .= '01 23:59:59';
                break;
            }
            $pwr = $moment['power'] / ($cost_divisor * 10);
            $cst = $moment['cost'] / $cost_divisor;
            $query = <<<EOQ
                INSERT INTO `$TED_DB_TABLE`
                (`ted_ts`, `mtu`, `hist`, `pwr`, `cost`)
                VALUES
                ('{$ted_ts}', {$mtu}, '{$hist}', {$pwr}, {$cst})
                ON DUPLICATE KEY UPDATE
                `pwr` = VALUES(`pwr`), `cost` = VALUES(`cost`);
EOQ;
            /*
            Insert the record
            */
            $mysqli->query($query);
            if (! $quiet || isset($log)) {
                switch ($mysqli->affected_rows) {
                    case 1: $inserted_rows += 1; break;
                    case 2: $updated_rows  += 1; break;
                    default: break;
                }
                $a++;
                /*
                Display progress for large updates
                */
                if($a % 25 == 0) echo ".";
                if($a % 1250 == 0) echo PHP_EOL;
            }
        }
        /*
        Output number of records updated and inserted
        */
        if (! $quiet) {
            echo
            "\tInserted ".number_format($inserted_rows)
            ." and updated ".number_format($updated_rows)
            ." SQL records from ".number_format($recs_found)
            ." TED records.".PHP_EOL;
        }
        /*
        Write statistics to a log file
        */
        if (isset($log)) {
            if (! fwrite($fp, date('c')
                .','.$mtu
                .','.$hist
                .','.$inserted_rows
                .','.$updated_rows
                .','.$recs_found
                .PHP_EOL)) die("Can't write to ".$log);
        }
    }
}
if (! $quiet)
        echo PHP_EOL
        ."  * Run time:".PHP_EOL."\tLess than "
        .number_format(time() - $start_ts + 1)
        ." seconds.".PHP_EOL;
/*
close connections
*/
if (isset($log)) fclose($fp);
$mysqli->close();
?>
