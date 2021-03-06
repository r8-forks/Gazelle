<?
if (!extension_loaded('mysqli')) {
	error('Mysqli Extension not loaded.');
}

class Sphinxql extends mysqli {
	private static $Connections = array();
	private $Server;
	private $Port;
	private $Socket;
	private $Ident;
	private $Connected = false;

	public static $Queries = array();
	public static $Time = 0.0;


	/**
	 * Initialize Sphinxql object
	 *
	 * @param string $Server server address or hostname
	 * @param int $Port listening port
	 * @param string $Socket Unix socket address, overrides $Server:$Port
	 */
	public function __construct($Server, $Port, $Socket) {
		$this->Server = $Server;
		$this->Port = $Port;
		$this->Socket = $Socket;
		$this->Ident = $this->get_ident($Server, $Port, $Socket);
	}

	/**
	 * Create server ident based on connection information
	 *
	 * @param string $Server server address or hostname
	 * @param int $Port listening port
	 * @param string $Socket Unix socket address, overrides $Server:$Port
	 * @return identification string
	 */
	private function get_ident($Server, $Port, $Socket) {
		if ($Socket) {
			return $Socket;
		} else {
			return "$Server:$Port";
		}
	}

	/**
	 * Create Sphinxql object or return existing one
	 *
	 * @param string $Server server address or hostname
	 * @param int $Port listening port
	 * @param string $Socket Unix socket address, overrides $Server:$Port
	 * @return Sphinxql object
	 */
	public static function init_connection($Server, $Port, $Socket) {
		$Ident = self::get_ident($Server, $Port, $Socket);
		if (!isset(self::$Connections[$Ident])) {
			self::$Connections[$Ident] = new Sphinxql($Server, $Port, $Socket);
		}
		return self::$Connections[$Ident];
	}

	/**
	 * Connect the Sphinxql object to the Sphinx server
	 */
	public function sphconnect() {
		if (!$this->Connected) {
			global $Debug;
			$Debug->set_flag('Connecting to Sphinx server '.$this->Ident);
			parent::__construct($this->Server, '', '', '', $this->Port, $this->Socket);
			if ($this->connect_error) {
				$Errno = $this->connect_errno;
				$Error = $this->connect_error;
				$this->error("Connection failed. ".strval($Errno)." (".strval($Error).")");
			}
			$Debug->set_flag('Connected to Sphinx server '.$this->Ident);
			$this->Connected = true;
		}
	}

	/**
	 * Print a message to privileged users and optionally halt page processing
	 *
	 * @param string $Msg message to display
	 * @param bool $Halt halt page processing. Default is to continue processing the page
	 * @return Sphinxql object
	 */
	public function error($Msg, $Halt = false) {
		global $Debug;
		$ErrorMsg = 'SphinxQL ('.$this->Ident.'): '.strval($Msg);
		$Debug->analysis('SphinxQL Error', $ErrorMsg, 3600*24);
		if ($Halt === true && (DEBUG_MODE || check_perms('site_debug'))) {
			echo '<pre>'.display_str($ErrorMsg).'</pre>';
			die();
		} elseif ($Halt === true) {
			error('-1');
		}
	}

	/**
	 * Escape special characters before sending them to the Sphinx server.
	 * Two escapes needed because the first one is eaten up by the mysql driver.
	 *
	 * @param string $String string to escape
	 * @return escaped string
	 */
	public function escape_string($String) {
		return strtr($String, array(
			'('=>'\\\\(',
			')'=>'\\\\)',
			'|'=>'\\\\|',
			'-'=>'\\\\-',
			'@'=>'\\\\@',
			'~'=>'\\\\~',
			'&'=>'\\\\&',
			'\''=>'\\\'',
			'<'=>'\\\\<',
			'!'=>'\\\\!',
			'"'=>'\\\\"',
			'/'=>'\\\\/',
			'*'=>'\\\\*',
			'$'=>'\\\\$',
			'^'=>'\\\\^',
			'\\'=>'\\\\\\\\')
		);
	}

	/**
	 * Register sent queries globally for later retrieval by debug functions
	 *
	 * @param string $QueryString query text
	 * @param param $QueryProcessTime time building and processing the query
	 */
	public function register_query($QueryString, $QueryProcessTime) {
		Sphinxql::$Queries[] = array($QueryString, $QueryProcessTime);
		Sphinxql::$Time += $QueryProcessTime;
	}
}
