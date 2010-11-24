<?php
/* by Michael Wetmore November 20 2010 */
$debug = FALSE;
/*
* TED Settings - ## are changed by this script to its requirements.
* The others should be changed to reflect your environment.
*/
$TED_HOSTNAME = 'ted5000';  # The hostname of the TED gateway device
$TED_PORT = 80;             # The port number of the TED gateway device
$TED_USERNAME = '';         # The authentication username
$TED_PASSWORD = '';         # The authentication password
$TED_SSL = FALSE;           # Enable/Disable SSL (TRUE or FALSE)
$TED_API = 'minutehistory'; ## livedata | secondhistory | minutehistory
                            ## | hour(ly)history | month(ly)history
$TED_MTU = 0;               ## The MTU number for data (0-3)
$TED_TYPE = 'all';          ## power | cost | voltage | all
$TED_FORMAT = 'raw';        ## raw | xml | csv
                            # NOTE: The livedata API only returns XML.
$TED_TABLE =
    'combined_history';     # the table to use
/*
* Database connection information
*/
$TED_DB_HOST = 'localhost'; # The database
$TED_DB_NAME = 'ted';       # Database name
$TED_DB_USER = 'root';      # Username to access the database
$TED_DB_PASS = 'password';  # Password to access the database
/*
* It would be nicer to be able to call the GetoptPlus help. How?
*/
function help() {
    echo PHP_EOL."TEDtoDB Usage:".PHP_EOL;
    echo PHP_EOL."\tREQUIRED:".PHP_EOL;
    echo "\t-i         's'econd | 'm'inute | 'h'our | 'd'ay | m'o'nth"
        .PHP_EOL;
    echo "\tOR".PHP_EOL;
    echo "\t--interval 'second' | 'minute' | 'hour' | 'day' | 'month'"
        .PHP_EOL;
    echo PHP_EOL."\tOPTIONAL:".PHP_EOL;
    echo "\t[ -q | --quiet ]\t\tsuppress all progress messages"
        .PHP_EOL;
    echo "\t[ -h | --help  ]\t\tHelp".PHP_EOL;
    echo PHP_EOL;
    echo "\tExample: ".basename($_SERVER['argv'][0])." -i s -q"
        .PHP_EOL;
    echo "\tExample: ".basename($_SERVER['argv'][0])
            ." --interval minute --quiet".PHP_EOL;
    exit;
}
/*
* This function is used to convert the SimpleXMLElement
* returned from a fetch of livedata into an array.
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
* Required stuff and where to get it
*/
require_once 'TED_PHP.class.php';
/* http://www.garrettbartley.com/TED_PHP/ */
require_once 'Console/Getoptplus.php';
/* http://pear.php.net/package/Console_GetoptPlus/ */
try {
    $config = array(
        'options' => array(
            array('long' => 'interval', 'type' => 'mandatory',
                'short' => 'i', 'desc' => array(
                    'arg',
                    "'s' or 'second' for second history from TED",
                    "'m' or 'minute' for minute history from TED",
                    "'h' or 'hour'   for hour history from TED",
                    "'d' or 'day'    for day history from TED",
                    "'o' or 'month'  for month history from TED",
                    ''
                )
            ),
            array('long' => 'quiet', 'type' => 'noarg', 'short'
                => 'q', 'desc' => array(
                'Suppress progress messages',
                ''
                )
            )
        ),
    );
    $options = Console_Getoptplus::getoptplus($config,
                        'short2long', true, 'shortcuts');
}
catch(Console_GetoptPlus_Exception $e) {
    echo $e->getMessage().PHP_EOL;
    help();
}
$quiet = FALSE;
if (count($options[0]) > 0) {
    foreach($options[0] as $option => $v)
        if ($option == 'quiet') $quiet = TRUE;
} else help();
switch (strtolower(trim($options[0]['interval']))) {
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
        $divisor = 60 * 60 * 24 * 28;
        $api = 'monthhistory';
        $intervalword = ' month';
        $interval = 4;
        $hist = 'o';
        break;
    default:
        echo "Invalid interval: "
            .strtolower($options[0]['interval']).PHP_EOL;
        help();
        break;
}
/* Start time for progress messsges */
if (! $quiet) $start_ts = time();
/* Set the century */
$century = substr(date('Y'), 0, 2);
/* Connect to the server and the database */
$mysqli = new mysqli(
        $TED_DB_HOST,
        $TED_DB_USER,
        $TED_DB_PASS,
        $TED_DB_NAME);
if ($mysqli->connect_error) {
    die('Connect Error ('.$mysqli->connect_errno.') '
        .$mysqli->connect_error);
}
/* Instantiate the TED_PHP object */
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
/* find out how many MTUs are installed */
$ted->set_api('livedata');
$live = simplexml2array($ted->fetch());
for ($i = 1; $i < 5; $i++)
    $mtulist[$i] =
        $live['Voltage']['MTU'.$i]['VoltageNow'];
/* now go and get the history data */
$ted->set_api($api);
$ted->set_format('raw');
foreach ($mtulist as $mtu => $volts) {
    if ($volts != 0) { // then the MTU is installed.
        /*
        * Get the number of seconds since the last update,
        * fall back on the time since TED-5000 introduction.
        * The subquery in the WHERE ensures that both
        * timestamps are from the same record.
        */
        $query = <<<EOQ
            SELECT
            UNIX_TIMESTAMP(NOW())
                - UNIX_TIMESTAMP(`ted_ts`) AS `last_update`,
            UNIX_TIMESTAMP(`ted_ts`)
                - UNIX_TIMESTAMP(`ins_ts`) AS 'max_offset'
            FROM `$TED_TABLE`
            WHERE `ted_ts` =
            (SELECT MAX(`ted_ts`) FROM `$TED_TABLE`
                WHERE `hist` = '$hist'
                AND `mtu` = $mtu)
            AND `hist` = '$hist'
            AND `mtu` = $mtu;
EOQ;
        $last_update = ceil((time()
                    - strtotime("1 June 2009")) / $divisor);
        $max_offset  = 0;
        if ($result = $mysqli->query($query, MYSQLI_STORE_RESULT)) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $last_update = ceil($row['last_update'] / $divisor);
                $max_offset  = ceil($row['max_offset'] / $divisor);
            }
        }
        $result->close();
        if ($max_offset < 0) $max_offset = 1;
        $records_to_get = $last_update + $max_offset + 1;
        if (! $quiet) {
            echo PHP_EOL."  * For MTU".$mtu.":".PHP_EOL
                ."\tLast update was "
                .number_format($last_update)
                .$intervalword.'s ago.'
                ."  Will retrieve "
                .number_format($records_to_get)
                .' records from TED.'.PHP_EOL;
        }
        $ted->set_mtu($mtu - 1);
        if (! $quiet && $debug) {
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
        if (! $quiet) {
            $a = 0;
            $inserted_rows = 0;
            $updated_rows = 0;
            $recs_found = count($moments);
        }
        /* Loop through the results and insert into the table */
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
                INSERT INTO `$TED_TABLE`
                (`ted_ts`, `mtu`, `hist`, `pwr`, `cost`)
                VALUES
                ('{$ted_ts}', {$mtu}, '{$hist}', {$pwr}, {$cst})
                ON DUPLICATE KEY UPDATE
                `pwr` = VALUES(`pwr`), `cost` = VALUES(`cost`);
EOQ;
            /* Insert the record */
            $mysqli->query($query);
            if (! $quiet) {
                switch ($mysqli->affected_rows) {
                    case 1: $inserted_rows += 1; break;
                    case 2: $updated_rows  += 1; break;
                    default: break;
                }
                $a++;
                /* Display progress for large updates */
                if($a % 25 == 0) echo ".";
                if($a % 1250 == 0) echo PHP_EOL;
            }
        }
        /* Output number of records updated and inserted */
        if (! $quiet) {
            echo
             "\tInserted ".number_format($inserted_rows)
            ." and updated ".number_format($updated_rows)
            ." SQL records from ".number_format($recs_found)
            ." TED records.".PHP_EOL;
        }
    }
}
if (! $quiet) 
        echo PHP_EOL
        ."  * Run time:".PHP_EOL."\tLess than "
        .number_format(time() - $start_ts + 1)
        ." seconds.".PHP_EOL;
/* close connection to database server */
$mysqli->close();
?>
