<?php

class FLM {

	public $hash = 'flm.dat';

	protected $xmlrpc;

	public $postlist = array('dir', 'action', 'file', 'fls', 'target', 'mode', 'to', 'format');

	public $workdir;
	public	$userdir;
	public $fman_path;

	private $output = array('errcode' => 0);
	protected $temp = array();
	protected $filelist;
	
	private $settings;

	protected $shout = TRUE;


	public function __construct() {
		global $topDirectory, $fm;

		$this->check_post($this->postlist);
		$this->userdir = addslash($topDirectory);
		$this->workdir = $this->userdir.$this->postlist['dir'];

		if(($this->postlist['dir'] === FALSE) || !is_dir($this->workdir)) { $this->output['errcode'] = 2; die();}
		elseif ($this->postlist['action'] === FALSE) { $this->sdie('No action defined');}

		$this->workdir = addslash($this->workdir);
		$this->fman_path = dirname(__FILE__);

		$this->xmlrpc = new rxmlrpcfix();

		$this->settings = $fm;
		$this->filelist = ($this->postlist['fls'] !== FALSE) ? $this->get_filelist($this->postlist['fls']) : '';

		if(!is_dir($this->settings['tempdir'])) {	$this->output['errcode'] = 17; die(); }
		$this->make_temp();

	}

	public function archive () {

		if (empty($this->filelist)) {$this->output['errcode'] = 22; return false;}

		$a['file'] = $this->userdir.$this->postlist['target'];

		if (($this->postlist['target'] === FALSE) || LFS::test($a['file'],'e')) {$output['errcode'] = 16; return false;}
		if(($this->postlist['mode'] === FALSE) || !isset($this->settings['archive']['types'][$this->postlist['mode']])) { $this->sdie('Invalid archive type');}

		$a['type'] = $this->settings['archive']['types'][$this->postlist['mode']];
		$a['comp'] = $this->settings['archive']['compress'][$this->postlist['mode']][$this->postlist['file']];
		$a['volume'] = (intval($this->postlist['to'])*1024);
		$a['multif'] = (($a['type'] == 'rar') && ($this->postlist['format'] == 'old')) ? '-vn' : '';

		switch($a['type']) {
			case 'rar':
				$bin = $this->settings['rar'];
				break;
			case 'zip':
				$bin = $this->settings['zip'];
				break;
			default:
				$this->sdie('Unknown compressor');
		}

		$this->batch_exec(array("sh", "-c", escapeshellarg($this->fman_path.'/scripts/archive')." ".escapeshellarg($bin)." ".
							escapeshellarg($this->temp['dir'])." ".escapeshellarg($a['file'])." ".
							escapeshellarg($a['type'])." ".escapeshellarg($a['comp'])." ".
							escapeshellarg($a['volume'])." ".escapeshellarg($a['multif'])));
	}


	public function batch_exec($what) {

		$lk = array_pop(array_keys($what));

		$what[$lk] .= ' '.$this->filelist.' &';

		$this->xmlrpc->addCommand(new rXMLRPCCommand("execute", $what));
		if($this->xmlrpc->success()) {$this->output['tmpdir'] = $this->temp['tok'];} else {$this->output['errcode'] = 23;}
	}


	public function check_post(&$what) {

		if((count($_POST) < 1) && ((PHP_SAPI == 'cli') || (PHP_SAPI == 'cgi'))) {
				foreach(file("php://input") as $key => $inv) {$_POST[$key] = rawurldecode($inv);}
		} 

		$dupe = $what;
		foreach($dupe as $k => $val) {
			unset($what[$k]);
			$what[$val] = (isset($_POST[$val]) && (trim($_POST[$val]) != '')) ? trim($_POST[$val], '/') : FALSE;
		}
	}


	public function copy($to) {
		if(($this->postlist['to'] === FALSE) || !is_dir($to)) {$this->output['errcode'] = 2; return false; }
		if (empty($this->filelist)) {$this->output['errcode'] = 22; return false;}

		$this->batch_exec(array("sh", "-c", escapeshellarg($this->fman_path.'/scripts/cp')." ".escapeshellarg($this->temp['dir'])." ".
							 escapeshellarg($to)));
	}


	static public function compare($a, $b) {return strcmp($a['name'], $b['name']);}

	public function dirlist() {

		$this->xmlrpc->addCommand( new rXMLRPCCommand('execute_capture', 
					array('find', $this->workdir, '-mindepth', '1', '-maxdepth', '1', '-printf', '%y\t%f\t%s\t%C@\t%#m\n')));

		if(!$this->xmlrpc->success()) {$this->output['errcode'] = 10; return false;}
		$this->output['listing'] = array();

		$i = 0;
		foreach (explode("\n", trim($this->xmlrpc->val[0])) as $fileline) {

			if(empty($fileline)) {continue;}
			$f = array();

			list($fd, $f['name'], $f['size'], $f['time'], $f['perm']) = explode("\t", trim($fileline));

			$f['name'] = stripslashes($f['name']);
			$f['time'] = intval($f['time']);

			if($fd == 'd') {	$f['name'] .= '/';
						$f['size'] = ''; }

			$this->output['listing'][$i] = $f;
			$i++;

		}

		usort($this->output['listing'], array('FLM', 'compare'));
	}


	public function dos_format ($ibm_437, $swedishmagic = false) {
		$table437 = array(	"\200", "\201", "\202", "\203", "\204", "\205", "\206", "\207",
					"\210", "\211", "\212", "\213", "\214", "\215", "\216", "\217", "\220",
					"\221", "\222", "\223", "\224", "\225", "\226", "\227", "\230", "\231",
					"\232", "\233", "\234", "\235", "\236", "\237", "\240", "\241", "\242",
					"\243", "\244", "\245", "\246", "\247", "\250", "\251", "\252", "\253",
					"\254", "\255", "\256", "\257", "\260", "\261", "\262", "\263", "\264",
					"\265", "\266", "\267", "\270", "\271", "\272", "\273", "\274", "\275",
					"\276", "\277", "\300", "\301", "\302", "\303", "\304", "\305", "\306",
					"\307", "\310", "\311", "\312", "\313", "\314", "\315", "\316", "\317",
					"\320", "\321", "\322", "\323", "\324", "\325", "\326", "\327", "\330",
					"\331", "\332", "\333", "\334", "\335", "\336", "\337", "\340", "\341",
					"\342", "\343", "\344", "\345", "\346", "\347", "\350", "\351", "\352",
					"\353", "\354", "\355", "\356", "\357", "\360", "\361", "\362", "\363",
					"\364", "\365", "\366", "\367", "\370", "\371", "\372", "\373", "\374",
					"\375", "\376", "\377");

		$tablehtml = array("&#x00c7;", "&#x00fc;", "&#x00e9;", "&#x00e2;", "&#x00e4;",
					"&#x00e0;", "&#x00e5;", "&#x00e7;", "&#x00ea;", "&#x00eb;", "&#x00e8;",
					"&#x00ef;", "&#x00ee;", "&#x00ec;", "&#x00c4;", "&#x00c5;", "&#x00c9;",
					"&#x00e6;", "&#x00c6;", "&#x00f4;", "&#x00f6;", "&#x00f2;", "&#x00fb;",
					"&#x00f9;", "&#x00ff;", "&#x00d6;", "&#x00dc;", "&#x00a2;", "&#x00a3;",
					"&#x00a5;", "&#x20a7;", "&#x0192;", "&#x00e1;", "&#x00ed;", "&#x00f3;",
					"&#x00fa;", "&#x00f1;", "&#x00d1;", "&#x00aa;", "&#x00ba;", "&#x00bf;",
					"&#x2310;", "&#x00ac;", "&#x00bd;", "&#x00bc;", "&#x00a1;", "&#x00ab;",
					"&#x00bb;", "&#x2591;", "&#x2592;", "&#x2593;", "&#x2502;", "&#x2524;",
					"&#x2561;", "&#x2562;", "&#x2556;", "&#x2555;", "&#x2563;", "&#x2551;",
					"&#x2557;", "&#x255d;", "&#x255c;", "&#x255b;", "&#x2510;", "&#x2514;",
					"&#x2534;", "&#x252c;", "&#x251c;", "&#x2500;", "&#x253c;", "&#x255e;",
					"&#x255f;", "&#x255a;", "&#x2554;", "&#x2569;", "&#x2566;", "&#x2560;",
					"&#x2550;", "&#x256c;", "&#x2567;", "&#x2568;", "&#x2564;", "&#x2565;",
					"&#x2559;", "&#x2558;", "&#x2552;", "&#x2553;", "&#x256b;", "&#x256a;",
					"&#x2518;", "&#x250c;", "&#x2588;", "&#x2584;", "&#x258c;", "&#x2590;",
					"&#x2580;", "&#x03b1;", "&#x00df;", "&#x0393;", "&#x03c0;", "&#x03a3;",
					"&#x03c3;", "&#x03bc;", "&#x03c4;", "&#x03a6;", "&#x0398;", "&#x03a9;",
					"&#x03b4;", "&#x221e;", "&#x03c6;", "&#x03b5;", "&#x2229;", "&#x2261;",
					"&#x00b1;", "&#x2265;", "&#x2264;", "&#x2320;", "&#x2321;", "&#x00f7;",
					"&#x2248;", "&#x00b0;", "&#x2219;", "&#x00b7;", "&#x221a;", "&#x207f;",
					"&#x00b2;", "&#x25a0;", "&#x00a0;");

		$s = htmlspecialchars($ibm_437);

		$control = array(	"\000", "\001", "\002", "\003", "\004", "\005", "\006", "\007",
					"\010", "\011", /*"\012",*/ "\013", "\014", /*"\015",*/ "\016", "\017",
					"\020", "\021", "\022", "\023", "\024", "\025", "\026", "\027",
					"\030", "\031", "\032", "\033", "\034", "\035", "\036", "\037",
					"\177");

		$s = str_replace($control," ",$s);

		if ($swedishmagic){
			$s = str_replace("\345","\206",$s); // Code windows "?" to dos.
			$s = str_replace("\344","\204",$s); // Code windows "�" to dos.
			$s = str_replace("\366","\224",$s); // Code windows "�" to dos.


			$s = ereg_replace("([ -~])\305([ -~])", "\\1\217\\2", $s); // ?
			$s = ereg_replace("([ -~])\304([ -~])", "\\1\216\\2", $s); // �
			$s = ereg_replace("([ -~])\326([ -~])", "\\1\231\\2", $s); // �

			$s = str_replace("\311", "\220", $s); // �
			$s = str_replace("\351", "\202", $s); // �
		}

		$s = str_replace($table437, $tablehtml, $s);
		return $s;
	}


	public function escape_fullpath(&$value) {

		// rm doesnt know, we do
		$trm = trim(trim($value, '/'));

		if($trm != '') {$value = escapeshellarg($this->workdir.$trm);} 
		else {$value = $trm;}
	}


	public function extract($archive, $target) {
		
		if(($archive === FALSE) || !LFS::is_file($this->userdir.$archive))  {$this->output['errcode'] = 6; return false; }
		if(($target === FALSE) || LFS::is_file($this->userdir.$target))  {$this->output['errcode'] = 16; return false; }

		switch($this->fext($archive)) {
			case 'rar':
				$bin = $this->settings['rar'];
				break;
			case 'zip':
				$bin = $this->settings['unzip'];
				break;
			default:
				$this->output['errcode'] = 18; return false; 
		}


		$this->batch_exec(array("sh", "-c", escapeshellarg($this->fman_path.'/scripts/extract')." ".escapeshellarg($bin)." ".
							escapeshellarg($this->temp['dir'])." ".escapeshellarg($this->userdir.$archive)." ".escapeshellarg($this->userdir.$target)));

	}

	public function fext($file) {
		return (pathinfo($file, PATHINFO_EXTENSION));
	}

	public function get_file($file, $large = FALSE) {

			set_time_limit(0);
			if ($large) {passthru('cat '.escapeshellarg($file), $err);} 
			else { readfile($file); }
			$this->sdie();
	}

	public function get_filelist($what) {

		$files = json_decode($what, true); 

		array_walk($files, array($this,'escape_fullpath')); 
		$filelist = implode(' ', $files);

		return $filelist;
	}

	public function get_session() {$this->output['sess'] = session_id();}

	public function kill($token) {

		if($token === FALSE) {$this->sdie('No token');}

		$k['tmp'] = addslash($this->settings['tempdir']).'.rutorrent/.fman/'.$token;
		$k['pid'] = $k['tmp'].'/pid';
		
		if(!is_file($k['pid'])) {$this->output['errcode'] = 19; return false;};

		$pid = file($k['pid']);
		$pid = trim($pid[0]);
		

		$this->xmlrpc->addCommand(new rXMLRPCCommand( "execute", array('sh', '-c', 'kill -15 -- '.$pid.' `pgrep -P '.$pid.'`')));
		$this->xmlrpc->addCommand(new rXMLRPCCommand( "execute", array("rm", "-rf", $k['tmp'])));
	
		if(!$this->xmlrpc->success()) {$this->output['errcode'] = 20;}
	}


	protected function make_temp() {

		$tmp['tok'] = getUser().time().rand(5, 20);
		$tmp['dir'] = addslash($this->settings['tempdir']).'.rutorrent/.fman/'.$tmp['tok'].'/';

		$this->temp = $tmp;

	}


	public function mediainfo ($file) {
		eval(getPluginConf('mediainfo'));

		if(($file === FALSE) || !LFS::is_file($this->workdir.$file))  {$this->output['errcode'] = 6; return false; }

		exec(getExternal("mediainfo").' --Output=HTML '.escapeshellarg($this->workdir.$file), $out, $failure);

        	if($failure) {$output['errcode'] = 14; return false;}

		$this->output['minfo'] = preg_replace("/.*<body[^>]*>|<\/body>.*/si", "", implode('', $out));
	}

	public function move($to) {

		if(($this->postlist['to'] === FALSE) || !is_dir($to)) {$this->output['errcode'] = 2; return false; }
		if (empty($this->filelist)) {$this->output['errcode'] = 22; return false;}

		$this->batch_exec(array("sh", "-c", escapeshellarg($this->fman_path.'/scripts/mv')." ".escapeshellarg($this->temp['dir'])." ".
							 escapeshellarg($to)));
	}


	public function mkdir() {

		if(($this->postlist['target'] === FALSE) || is_dir($this->workdir.$this->postlist['target'])) {$this->output['errcode'] = 16; return false;}

		$this->xmlrpc->addCommand(new rXMLRPCCommand('execute', array('mkdir', '--mode='.$this->settings['mkdperm'], 
											$this->workdir.$this->postlist['target'])));

		if(!$this->xmlrpc->success()) {$this->output['errcode'] = 4;} 

	}

	public function nfo_get($nfofile, $dos = TRUE) {

		if (!is_file($this->workdir.$nfofile)) 	{ $this->output['errcode'] = 6; return false;}
		elseif (($this->fext($nfofile) != 'nfo') || (filesize($this->userhome.$nfofile) > 50000)) {$this->output['errcode'] = 18; return false;}
		elseif (($fc = $this->read_file($nfofile, FALSE)) === FALSE) { $this->output['errcode'] = 3; die();}

		$this->output['nfo'] = $dos ? $this->dos_format($fc, TRUE) : htmlentities($fc);

	}


	public function read_file($file, $array = TRUE) {
		
		return $array ? file($this->workdir.$file, FILE_IGNORE_NEW_LINES) : file_get_contents($this->workdir.$file); 

	}


	public function readlog($session, $lpos) {

		$log['pos'] = (filter_var($lpos, FILTER_VALIDATE_INT) !== FALSE) ? $lpos : 0;
		$log['file'] = addslash($this->settings['tempdir']).'.rutorrent/.fman/'.$session.'/log';

		if(!is_file($log['file'])) {$this->output['errcode'] = 21; return false;};

		$log['contents'] = file($log['file']);
		$log['slice'] = array_slice($log['contents'], $log['pos']);

		$this->output['lp'] = $log['pos'] + count($log['slice']);
		$this->output['status'] = (trim(substr(end($log['contents']),0, 2)) == 1) ? 1 : 0;

		$this->output['lines'] = '';

		foreach ($log['slice'] as $line) {
			$this->output['lines'] .= trim(substr($line, 2, -1))."\n";
		}
	}


	public function rename() {

		if(($this->postlist['target'] === FALSE) || ($this->postlist['to'] === FALSE)) {$this->output['errcode'] = 18; return false;}

		$what = $this->workdir.$this->postlist['target'];
		$to = $this->workdir.$this->postlist['to'];

		if (!LFS::test($what,'e') || LFS::test($to,'e')) {$this->output['errcode'] = 18; return false;}

		$this->xmlrpc->addCommand(new rXMLRPCCommand('execute', array('mv', '-f', $what, $to)));

		if(!$this->xmlrpc->success()) {$this->output['errcode'] = 8; } 

	}


	public function remove() {

		if(empty($this->filelist)) {$this->output['errcode'] = 22; return false;}
		$this->batch_exec(array("sh", "-c", escapeshellarg($this->fman_path.'/scripts/rm')." ".escapeshellarg($this->temp['dir'])));
	}

	public function send_file($file) {

		$fpath = $this->workdir.$file;
		$this->shout = FALSE;

		if(($file === FALSE) || (($finfo = LFS::stat($fpath)) === FALSE)) {cachedEcho('log(theUILang.fErrMsg[6]+" - '.$fpath.'");',"text/html");}


		if(($finfo['size'] <= 2147483647) && !ini_get("zlib.output_compression")) {header("Content-Length: ".$finfo['size']);}

		$etag = sprintf('"%x-%x-%x"', $finfo['ino'], $finfo['size'], $finfo['mtime'] * 1000000);


		if( 	(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) ||
                        	(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $finfo['mtime'])) {

			header('HTTP/1.0 304 Not Modified');
		} else {
			header('Etag: '.$etag);
			header('Last-Modified: ' . date('r', $finfo['mtime']));
		}

		header('Cache-Control: ');
		header('Expires: ');
		header('Pragma: ');
		header('Accept-Ranges: bytes');
		header('Content-Type: application/octet-stream');

		header('Content-Disposition: attachment; filename="'.end(explode('/',$file)).'"');
		header('Content-Transfer-Encoding: binary');
		header('Content-Description: File Transfer');

		ob_end_flush();

		$this->get_file($fpath, ($finfo['size'] >= 2147483647));


	}

	
	public function sfv_check ($file) {

		if ($this->fext($file) != 'sfv') 	{ $this->output['errcode'] = 18; return false;}
		elseif (!is_file($this->userdir.$file)) 	{ $this->output['errcode'] = 6; return false;}

		$this->batch_exec(array("sh", "-c", escapeshellarg(getPHP())." ".escapeshellarg($this->fman_path.'/scripts/sfvcheck.php')." ".
							escapeshellarg($this->temp['dir'])." ".escapeshellarg($this->userdir.$file)));
	}



	public function sfv_create ($file) {

		if (empty($this->filelist)) {$this->output['errcode'] = 22; return false;}
		if(LFS::test($this->userdir.$file,'e')) {$output['errcode'] = 16; return false;}

		$this->batch_exec(array("sh", "-c", escapeshellarg(getPHP())." ".escapeshellarg($this->fman_path.'/scripts/sfvcreate.php')." ".
							escapeshellarg($this->temp['dir'])." ".escapeshellarg($this->userdir.$file)));
	}

	public function stream($file) {

		if (!preg_match('/^(avi|divx|mpeg|mp4|mkv)$/i', $this->fext($file))) {$this->sdie('404 Invalid format');}

		if (!is_file($this->workdir.$file)) {$this->sdie('404 File not found');}

		header('Content-Type: video/divx');
		header('Content-Disposition: inline; filename="'.$file.'"');
		header('Content-Length: '.filesize($this->workdir.$file));
		ob_end_flush();

		$this->get_file($this->workdir.$file);
	}


	public function sdie($args = '') {

		$this->shout = FALSE;
		die($args);
	}


  	public function __destruct() {

		if($this->shout) {cachedEcho(json_encode($this->output));}
   	}


}


?>