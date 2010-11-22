<?php
/* Original by Garrett Bartley */
class TED_PHP {
	private $host,
		$port,
		$username,
		$password,
		$ssl,
		$api,
		$mtu,
		$type,
		$format,
		$url,
		$curl;
	function __construct(
		/* defaults */
		$host = 'TED5000',
		$port = 80,
		$username = '',
		$password = '',
		$ssl = FALSE,
		$api = 'minutehistory',
		$mtu = 0,
		$type = 'all',
		$format = 'raw'
		) {		
			/* Set values passed when class is called */
			$this->set_host($host);
			$this->set_port($port);
			$this->set_username($username);
			$this->set_password($password);
			$this->set_ssl($ssl);
			$this->set_api($api);
			$this->set_type($type);
			$this->set_mtu($mtu);
			$this->set_format($format);
			/* Initialize cURL */
			$this->init_curl();
	}
	function __destruct() {
		/* Close the cURL session if it's open */
		if ($this->curl) {
			curl_close($this->curl);
			unset($this->curl);
		}
	}
	/* Set the gateway hostname */
	public function set_host($host = '') {
		$host = trim($host);
		if (strlen($host) > 0)
			$this->host = $host;
	}
	/* Set the gateway port */
	public function set_port($port = 0) {
		$port = intval($port);
		if ($port > 0 || $port < 65535)
			$this->port = $port;
	}
	/* Enable/Disable SSL */
	public function set_ssl($ssl = FALSE) {
		if ($ssl === TRUE || $ssl === FALSE)
			$this->ssl = $ssl;
	}
	/* Set the gateway authentication username */
	public function set_username($username = '') {
		$this->username = trim($username);
	}
	/* Set the gateway authentication password */
	public function set_password($password = '') {
		$this->password = trim($password);
	}
	/* Set the MTU number */
	public function set_mtu($mtu = 0) {
		$mtu = intval($mtu);
		if ($mtu >= 0)
			$this->mtu = $mtu;
	}
	/* Set the type of data to be returned
	(power, cost, voltage, all) */
	public function set_type($type = '') {
		$type = strtolower(trim($type));
		if (strlen($type) > 0 && ($type == 'power'
		|| $type == 'cost'
		|| $type == 'voltage'
		|| $type == 'all'))
			$this->type = $type;
	}
	/* Set which API to query */
	public function set_api($api = 'minutehistory') {
		$api = strtolower(trim($api));
		if (strlen($api) > 0
		&& ($api == 'livedata'
		|| $api == 'secondhistory'
		|| $api == 'minutehistory'
		|| $api == 'hourlyhistory'
		|| $api == 'dailyhistory'
		|| $api == 'monthlyhistory'
		|| $api == 'hourhistory'
		|| $api == 'dayhistory'
		|| $api == 'monthhistory'))
			$this->api = $api;
	}
	/* Set the return format. Raw is faster, thus recommended */
	public function set_format($format = 'raw') {
		$format = strtolower(trim($format));
		if (strlen($format) > 0
		&& ($format == 'raw'
		|| $format == 'xml'
		|| $format == 'csv'))
			$this->format = $format;
	}
	/* Get the host */
	public function get_host() {
		return $this->host;
	}
	/* Get the port */
	public function get_port() {
		return $this->port;
	}
	/* Get SSL */
	public function get_ssl() {
		return $this->ssl;
	}
	/* Get the username */
	public function get_username() {
		return $this->username;
	}
	/* Get the password */
	public function get_password() {
		if (strlen($this->password > 0))
			return '******';
		else
			return $this->password;
	}
	/* Get the MTU */
	public function get_mtu() {
		return $this->mtu;
	}
	/* Get the type */
	public function get_type() {
		return $this->type;
	}
	/* Get the API */
	public function get_api() {
		return $this->api;
	}
	/* Get the format */
	public function get_format() {
		return $this->format;
	}
	/* Build the API request URL from all the specified options */
	private function init_url($index = 0, $count = 0) {
		$querystring = array();
		$index = intval($index);
		$count = intval($count);
		/* Enable/disable SSL */
		if ($this->ssl === TRUE)
			$retval = 'https://';
		else
			$retval = 'http://';
		$retval .= $this->host.':'.$this->port.'/';
		/* If we want the LiveData.xml, set the format for XML */
		if ($this->api == 'livedata') {
			$this->format = 'xml';
			$retval .= 'api/LiveData.xml';
		} else {
			/* Otherwise, we probably want a history */
			$retval .= 'history/';
			if ($this->format == 'raw') {
				$retval .= 'raw';
				/* And, due to the inconsistencies between the XML/CSV
				* files and raw files, we make some corrections */
				if ($this->api == 'hourlyhistory')
					$this->api = 'hourhistory';
				elseif ($this->api == 'dailyhistory')
					$this->api = 'dayhistory';
				elseif ($this->api == 'monthlyhistory')
					$this->api = 'monthhistory';
			}
			/* Make sure it's lower-cased */
			$retval .= strtolower($this->api);
			/* Append the file extension */
			switch($this->format) {
				case 'raw':
					$retval .= '.raw';
					break;
				case 'csv':
					$retval .= '.csv';
					break;
				default:
					$retval .= '.xml';
			}
			/* Add MTU to the query string */
			if ($this->mtu > 0) $querystring['MTU'] = $this->mtu;
		}
		/* Add INDEX to the query string */
		if ($index > 0) $querystring['INDEX'] = $index;
		/* Add COUNT to the query string */
		if ($count > 0) $querystring['COUNT'] = $count;
		/* If we have a query string, build it! */
		if (count($querystring) > 0) {
			$retval .= '?';
			$a = 0;
			foreach ($querystring as $q_k => $q_v) {
				$retval .= $q_k.'='.$q_v;
				$a++;
				if ($a < count($querystring)) $retval .= '&';
			}
		}
		$this->url = $retval;
	}
	/* Initialize cURL */
	private function init_curl() {
		$retval = curl_init();
		/* Fairly safe set of options */
		curl_setopt_array($retval, array(
			CURLOPT_URL => $this->url,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HEADER => FALSE,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_FAILONERROR => TRUE,
			CURLOPT_HTTPGET => TRUE,
			CURLOPT_NOPROGRESS => TRUE,
			CURLOPT_PORT => $this->port
		));
		/* Enable/Disable SSL */
		switch($this->ssl) {
			case TRUE:
				curl_setopt($retval, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
				break;
			default:
			curl_setopt($retval, CURLOPT_PROTOCOLS, CURLPROTO_HTTP);
		}
		/* Authentication */
		if (strlen($this->username) > 0 && strlen($this->password > 0))
			curl_setopt($retval,
				CURLOPT_USERPWD, $this->username.':'.$this->password);
		$this->curl = $retval;
	}
	/* Do a basic fetch */
	public function fetch($index = 0, $count = 0, $rawresponse = FALSE) {
		$retval = '';		
		/* build the URL */
		$this->init_url($index, $count);
		curl_setopt($this->curl, CURLOPT_URL, $this->url);
		/* Make the call */
		$retval = trim(curl_exec($this->curl));
		/* No data was returned.  Usually means it timed out */
		if (strlen($retval) == 0) {
			echo "no data\n";
			return FALSE;
		}
		/* If we want to pass back the raw response to the caller */
		if ($rawresponse === TRUE)
			return $retval;
		else
			/* Otherwise, process the response depending on format */
			switch($this->format) {
				case 'raw':
					return $this->decode_raw_fetch($retval);
					break;
				case 'csv':
					return $this->decode_csv_fetch($retval);
					break;
				default:
					return $this->decode_xml_fetch($retval);
			}
	}
	private function decode_raw_fetch($str = '') {
		$retval = array();
		$str = trim($str);
		/* Turn the response into a line-by-line array */
		$lines = explode("\n", $str);
		$arr = array();
		/* Loop */
		foreach ($lines as $line) {
			$line = trim($line);
			/* Add the trailing == in case it was left out */
			if (substr($line, -2) != '==')
				$line .= '==';
			$packstr = '';
			switch($this->api) {
				/* Seconds */
				case 'secondhistory':
					$packstr =
						'Cyear/'
						.'Cmonth/'
						.'Cday/'
						.'Chour/'
						.'Cminute/'
						.'Csecond/'
						.'lpower/'
						.'lcost/'
						.'svoltage';
					$arr = unpack($packstr, base64_decode($line));
					$arr['voltage'] = $arr['voltage'] / 20;
					break;
				/* Minutes */
				case 'minutehistory':
					$packstr =
						'Cyear/'
						.'Cmonth/'
						.'Cday/'
						.'Chour/'
						.'Cminute/'
						.'lpower/'
						.'lcost/'
						.'svoltage';
					$arr = unpack($packstr, base64_decode($line));
					$arr['voltage'] = $arr['voltage'] / 20;
					break;
				/* Hours */
				case 'hourhistory':
					$packstr =
						'Cyear/'
						.'Cmonth/'
						.'Cday/'
						.'Chour/'
						.'lpower/'
						.'lcost/'
						.'svoltage_high/'
						.'svoltage_low';
					$arr = unpack($packstr, base64_decode($line));
					$arr['voltage_low'] = $arr['voltage_low'] / 20;
					$arr['voltage_high'] = $arr['voltage_high'] / 20;
					break;
				/* Days */
				case 'dayhistory':
					$packstr =
						'Cyear/'
						.'Cmonth/'
						.'Cday/'
						.'lpower/'
						.'lcost/'
						.'spower_low/'
						.'Cpower_low_hour/'
						.'Cpower_low_min/'
						.'spower_high/'
						.'Cpower_high_hour/'
						.'Cpower_high_min/'
						.'scost_low/'
						.'Ccost_low_hour/'
						.'Ccost_low_min/'
						.'scost_high/'
						.'Ccost_high_hour/'
						.'Ccost_high_min/'
						.'svoltage_low/'
						.'Cvoltage_low_hour/'
						.'Cvoltage_low_min/'
						.'svoltage_high/'
						.'Cvoltage_high_hour/'
						.'Cvoltage_high_min';
					$arr = unpack($packstr, base64_decode($line));
					$arr['voltage_low'] = $arr['voltage_low'] / 20;
					$arr['voltage_high'] = $arr['voltage_high'] / 20;
					break;
				/* Months */
				case 'monthhistory':
					$packstr =
						'Cyear/'
						.'Cmonth/'
						.'lpower/'
						.'lcost/'
						.'spower_low/'
						.'Cpower_low_month/'
						.'Cpower_low_day/'
						.'spower_high/'
						.'Cpower_high_month/'
						.'Cpower_high_day/'
						.'scost_low/'
						.'Ccost_low_month/'
						.'Ccost_low_day/'
						.'scost_high/'
						.'Ccost_high_month/'
						.'Ccost_high_day/'
						.'svoltage_low/'
						.'Cvoltage_low_month/'
						.'Cvoltage_low_day/'
						.'svoltage_high/'
						.'Cvoltage_high_month/'
						.'Cvoltage_high_day';
					$arr = unpack($packstr, base64_decode($line));					
					$arr['voltage_low'] = $arr['voltage_low'] / 20;
					$arr['voltage_high'] = $arr['voltage_high'] / 20;
					break;
			}
			switch($this->type) {
				case 'power':
					foreach (array_keys($arr) as $k)
						if (stripos($k,'voltage') !== FALSE
						|| stripos($k,'cost') !== FALSE)
							unset($arr[$k]);
					break;
				case 'cost':
					foreach (array_keys($arr) as $k)
						if (stripos($k,'power') !== FALSE
						|| stripos($k,'voltage') !== FALSE)
							unset($arr[$k]);
					break;
				case 'voltage':
					foreach (array_keys($arr) as $k)
						if (stripos($k,'power') !== FALSE
						|| stripos($k,'cost') !== FALSE)
							unset($arr[$k]);
					break;
			}
			$retval[] = $arr;
		}
		return $retval;
	}
	private function decode_csv_fetch($str = '') {
		$retval = array();
		$str = trim($str);
		/* Turn the response into a line-by-line array */
		$lines = explode("\n", $str);
		/* Loop! */
		$headers = FALSE;
		foreach ($lines as $line) {
			$line = str_getcsv($line);
			/* If we don't have headers yet, it means we're on
			the first line and should use that */
			if (!$headers) {
				$headers = $line;
				continue;
			}
			/* Build the array */
			$arr = array();
			for($a = 0; $a<count($line); $a++)
				$arr[$headers[$a]] = $line[$a];
			/* There seems to be an empty line that is
			translated as one field -- ignore it */
			if (count($line) > 1)
				$retval[] = $arr;
		}
		return $retval;
	}
	private function decode_xml_fetch($str) {
		$retval = array();
		$staging = array();
		$str = trim($str);
		/* Convert to object */
		$xml = new SimpleXMLElement($str);
		if ($this->api != 'livedata') {
			/* Remove the top-level */
			$xml = (array)$xml;
			$key = array_keys($xml);
			$xml = $xml[$key[0]];
			/* Convert everything to an array */
			foreach (array_keys($xml) as $x_v)
				$staging[] = (array)$xml[$x_v];
			/* Make the array keys lower-cased */
			foreach ($staging as $s_k => $s_v)
				$retval = array(strtolower($s_k) => $s_v);
		} else
			$retval = $xml;
		return $retval;
	}
}
?>
