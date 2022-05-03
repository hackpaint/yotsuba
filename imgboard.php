<?
if(file_exists('/www/global/lockdown')) {
	if($_COOKIE['4chan_auser'] && $_COOKIE['4chan_apass'] && ($_POST['mode']=='usrdel'||$_GET['mode']=='latest')) {
		// ok
	} 
	else {
		 die('Posting temporarily disabled. Come back later!<br/>&mdash;Team 4chan (uptime? what\'s that?)');
	}
}
include_once "./yotsuba_config.php";
include_once "./strings_e.php";
//include("./postfilter.php");
//include("./ads.php");
define('SQLLOGBAN', 'banned_users');		//Table (NOT DATABASE) used for holding banned users
define('SQLLOGMOD', 'mod_users');		//Table (NOT DATABASE) used for holding mod users
define('SQLLOGDEL', 'del_log');		//Table (NOT DATABASE) used for holding deletion log

if(BOARD_DIR == 'test') {
 ini_set('display_errors', 1);
}
extract($_POST);
extract($_GET);
extract($_COOKIE);

$id = intval($id);

if(array_key_exists('upfile',$_FILES)) {
$upfile_name=$_FILES["upfile"]["name"];
$upfile=$_FILES["upfile"]["tmp_name"];
}
else {
$upfile_name=$upfile='';
}

$path = realpath("./").'/'.IMG_DIR;
ignore_user_abort(TRUE);

if(WORD_FILT&&file_exists("wf.php")){include_once("wf.php");}
if(JANITOR_BOARD == 1) 
	include_once '/www/global/plugins/broomcloset.php';

//mysqli_board_connect();


if(!$con=mysqli_connect(SQLHOST,SQLUSER,SQLPASS)){
  echo S_SQLCONF;	//unable to connect to DB (wrong user/pass?)
  exit;
}

$db_id=mysqli_select_db($con, SQLDB); 
  if(!$db_id){echo S_SQLDBSF;}

if (!table_exist($con, SQLLOG)) {
  echo (SQLLOG.S_TCREATE);
  $result = mysqli_board_call($con, "create table ".SQLLOG." (primary key(no),
    no    int not null auto_increment,
    now   text,
    name  text,
    email text,
    sub   text,
    com   text,
    host  text,
    pwd   text,
    filename text,
    ext   text,
    w     int,
    h     int,
    tn_w  int,
    tn_h  int,
    tim   text,
    time  int,
    md5   text,
    fsize int,
    root  timestamp,
    resto int,
    sticky int,
    permasage int,
    closed int)");
  if(!$result){echo S_TCREATEF;}
}

// https://www.php.net/manual/en/class.mysqli-result.php
function mysqli_result($result,$row,$field=0) {
    if ($result===false) return false;
    if ($row>=mysqli_num_rows($result)) return false;
    if (is_string($field) && !(strpos($field,".")===false)) {
        $t_field=explode(".",$field);
        $field=-1;
        $t_fields=mysqli_fetch_fields($result);
        for ($id=0;$id<mysqli_num_fields($result);$id++) {
            if ($t_fields[$id]->table==$t_field[0] && $t_fields[$id]->name==$t_field[1]) {
                $field=$id;
                break;
            }
        }
        if ($field==-1) return false;
    }
    mysqli_data_seek($result,$row);
    $line=mysqli_fetch_array($result);
    return isset($line[$field])?$line[$field]:false;
}

// truncate $str to $max_lines lines and return $str and $abbr
// where $abbr = whether or not $str was actually truncated
function abbreviate($str,$max_lines) {
	if(!defined('MAX_LINES_SHOWN')) {
		if(defined('BR_CHECK')) {
		define('MAX_LINES_SHOWN', BR_CHECK);
		} else {
		define('MAX_LINES_SHOWN', 20);
		}
		$max_lines = MAX_LINES_SHOWN;
	}
	$lines = explode("<br />", $str);
	if(count($lines) > $max_lines) {
		$abbr = 1;
		$lines = array_slice($lines, 0, $max_lines);
		$str = implode("<br />", $lines);
	} else {
		$abbr = 0;
	}
	
	//close spans after abbreviating
	//XXX will not work with more html - use abbreviate_html from shiichan
	$str .= str_repeat("</span>", substr_count($str, "<span") - substr_count($str, "</span"));
	
	return array($str, $abbr);
}


// print $contents to $filename by using a temporary file and renaming it 
// (makes *.html and *.gz if USE_GZIP is on)
function print_page($filename,$contents,$force_nogzip=0) {
	$gzip = (USE_GZIP == 1 && !$force_nogzip);
	$tempfile = tempnam(realpath(RES_DIR), "tmp"); //note: THIS actually creates the file
	file_put_contents($tempfile, $contents, FILE_APPEND);
	rename($tempfile, $filename);
	chmod($filename, 0664); //it was created 0600

	if($gzip) {	
		$tempgz = tempnam(realpath(RES_DIR), "tmp"); //note: THIS actually creates the file
		$gzfp = gzopen($tempgz, "w");
		gzwrite($gzfp, $contents);
		gzclose($gzfp);
		rename($tempgz, $filename . '.gz');
		chmod($filename . '.gz', 0664); //it was created 0600
	}
}

function file_get_contents_cached($filename) {
	static $cache = array();
	if(isset($cache[$filename]))
		return $cache[$filename];
	//$cache[$filename] = file_get_contents($filename);
	return $cache[$filename];
}
	
function blotter_contents() {
	static $cache;
    global $con;
	if(isset($cache)) return $cache;
	$ret = "";
	$topN = 4; //how many lines to print
	$bl_lines = file( BLOTTER_PATH );
	$bl_top = array_slice($bl_lines, 0, $topN);
	$date = "";
	foreach($bl_top as $line) {
			if(!$date) {
				$lineparts = explode(' - ', $line);
				if(strpos($lineparts[0],'<font')!==FALSE) {
					$dateparts = explode('>', $lineparts[0]);
					$date = $dateparts[1];
					$date = "<li><font color=\"red\">Blotter updated: $date</font>";
				}
				else {
					$date = $lineparts[0];
					$date = "<li>Blotter updated: $date";
				}
			}
			$line = trim($line);
			$line = str_replace("\\", "\\\\", $line);
			$line = str_replace("'", "\'", $line);
			$ret .= "'<li>$line'+\n";
	}
	$ret .= "''";
	$cache = array($date,$ret);
	return array($date,$ret);
}

// insert into the rapidsearch queue
function rapidsearch_insert($board, $no, $body) {
    global $con;
	$board = mysqli_real_escape_string($board);
	$no = (int)$no;
	$body = mysqli_real_escape_string($body);
	mysqli_board_call($con, "INSERT INTO rs.rsqueue (`board`,`no`,`ts`,`com`) VALUES ('$board',$no,NOW(),'$body')");
}

function find_match_and_prefix($regex, $str, $off, &$match)
{
	if (!preg_match($regex, $str, $m, PREG_OFFSET_CAPTURE, $off)) return FALSE;
	
	$moff = $m[0][1];
	$match = array(substr($str, $off, $moff-$off), $m[0][0]);

	return TRUE;
}

function spoiler_parse($com) {
	if (!find_match_and_prefix("/\[spoiler\]/", $com, 0, $m)) return $com;
	
	$bl = strlen("[spoiler]"); $el = $bl+1;
	$st = '<span class="spoiler" onmouseover="this.style.color=\'#FFF\';" onmouseout="this.style.color=this.style.backgroundColor=\'#000\'" style="color:#000;background:#000">';
	$et = '</span>';
	$ret = $m[0].$st; $lev = 1;
	$off = strlen($m[0]) + $bl;
	
	while (1) {
		if (!find_match_and_prefix("@\[/?spoiler\]@", $com, $off, $m)) break;
		list($txt, $tag) = $m;
		
		$ret .= $txt;
		$off += strlen($txt) + strlen($tag);
		
		if ($tag == "[spoiler]") {
			$ret .= $st;
			$lev++;
		} else if ($lev) {
			$ret .= $et;
			$lev--;
		}
	}
	
	$ret .= substr($com, $off, strlen($com)-$off);
	$ret .= str_repeat($et, $lev);

	return $ret;
}

//rebuild the bans in array $boards
function rebuild_bans($boards) {
    $cmd = "nohup /usr/local/bin/suid_run_global bin/rebuildbans $boards >/dev/null 2>&1 &";
	exec($cmd);
}

function append_ban($board, $ip) {
    $cmd = "nohup /usr/local/bin/suid_run_global bin/appendban $board $ip >/dev/null 2>&1 &";
	exec($cmd);
}

// check whether the current user can perform $action (on $no, for some actions)
// board-level access is cached in $valid_cache.
function valid($action='moderator', $no=0){
    global $con;
	static $valid_cache; // the access level of the user
	$access_level = array('none' => 0, 'janitor' => 1, 'janitor_this_board' => 2, 'moderator' => 5, 'manager' => 10, 'admin' => 20);
	if(!isset($valid_cache)) {
		$valid_cache = $access_level['none'];
		if(isset($_COOKIE['4chan_auser'])&&isset($_COOKIE['4chan_apass'])){
			$user = mysqli_real_escape_string($_COOKIE['4chan_auser']);
			$pass = mysqli_real_escape_string($_COOKIE['4chan_apass']);
		}
		if($user&&$pass) {
			$result=mysqli_board_call($con, "SELECT allow,deny FROM ".SQLLOGMOD." WHERE username='$user' and password='$pass'");
			list($allow,$deny) = mysqli_fetch_row($result);
			mysqli_free_result($result);
			if($allow) {
				$allows = explode(',', $allow);
				$seen_janitor_token = false;
				// each token can increase the access level,
				// except that we only know that they're a moderator or a janitor for another board
				// AFTER we read all the tokens
				foreach($allows as $token) {
					if($token == 'janitor')
						$seen_janitor_token = true;
					else if($token == 'manager' && $valid_cache < $access_level['manager'])
						$valid_cache = $access_level['manager'];
					else if($token == 'admin' && $valid_cache < $access_level['admin'])
						$valid_cache = $access_level['admin'];
					else if(($token == BOARD_DIR || $token == 'all') && $valid_cache < $access_level['janitor_this_board'])
						$valid_cache = $access_level['janitor_this_board']; // or could be moderator, will be increased in next step
				}
				// now we can set moderator or janitor status 
				if(!$seen_janitor_token) {
					if($valid_cache < $access_level['moderator'])
						$valid_cache = $access_level['moderator'];
				}
				else {
					if($valid_cache < $access_level['janitor'])
						$valid_cache = $access_level['janitor'];
				}
				if($deny) {
					$denies = explode(',', $deny);
					if(in_array(BOARD_DIR,$denies)) {
						$valid_cache = $access_level['none'];
					}
				}
			}
		}
	}
	switch($action) {
		case 'moderator':
			return $valid_cache >= $access_level['moderator'];
		case 'textonly':
			return $valid_cache >= $access_level['moderator'];
		case 'janitor_board':
			return $valid_cache >= $access_level['janitor'];
		case 'delete':
			if($valid_cache >= $access_level['janitor_this_board']) {
				return true;
			}
			// if they're a janitor on another board, check for illegal post unlock			
			else if($valid_cache >= $access_level['janitor']) {
				$query=mysqli_board_call($con, "SELECT COUNT(*) from reports WHERE board='".BOARD_DIR."' AND no=$no AND cat=2");
				$illegal_count = mysqli_result($query, 0, 0);
				mysqli_free_result($query);
				return $illegal_count >= 3;
			}
		case 'reportflood':
			return $valid_cache >= $access_level['janitor'];
		case 'floodbypass':
			return $valid_cache >= $access_level['moderator'];
		default: // unsupported action
			return false;
	}
}

function sticky_post($no, $position) {
	global $log; log_cache();
    global $con;
	$post_sticknum="202701010000".sprintf("%02d",$position);
	$log[$no]['root'] = $post_sticknum;
	$log[$no]['sticky'] = '1';
	mysqli_board_call('UPDATE '.SQLLOG." SET sticky='1'".
				", root='".$post_sticknum."'".
				" WHERE no='".mysqli_real_escape_string($no)."'");
}

function permasage_post($no) {
	global $log; log_cache();
    global $con;
	$log[$no]['permasage'] = '1';
	mysqli_board_call('UPDATE '.SQLLOG." SET permasage='1'".
				" WHERE no='".mysqli_real_escape_string($no)."'");
}

function rebuildqueue_create_table() {
    global $con;
	$sql = <<<EOSQL
CREATE TABLE `rebuildqueue` (
  `board` char(4) NOT NULL,
  `no` int(11) NOT NULL,
  `ownedby` int(11) NOT NULL default '0',
  `ts` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`board`,`no`,`ownedby`)
)
EOSQL;
	mysqli_board_call($con, $sql);
}

function rebuildqueue_add($no) {
    global $con;
	$board = BOARD_DIR;
	$no = (int)$no;
	for($i=0;$i<2;$i++)
		if(!mysqli_board_call($con, "INSERT IGNORE INTO rebuildqueue (board,no) VALUES ('$board','$no')"))
			rebuildqueue_create_table();
		else
			break;
}

function rebuildqueue_remove($no) {
    global $con;
	$board = BOARD_DIR;
	$no = (int)$no;
	for($i=0;$i<2;$i++)
		if(!mysqli_board_call($con, "DELETE FROM rebuildqueue WHERE board='$board' AND no='$no'"))
			rebuildqueue_create_table();
		else
			break;
}

function rebuildqueue_take_all() {
    global $con;
	$board = BOARD_DIR;
	$uid = mt_rand(1, mt_getrandmax());
	for($i=0;$i<2;$i++)
		if(!mysqli_board_call($con, "UPDATE rebuildqueue SET ownedby=$uid,ts=ts WHERE board='$board' AND ownedby=0"))
			rebuildqueue_create_table();
		else
			break;
	$q = mysqli_board_call($con, "SELECT no FROM rebuildqueue WHERE board='$board' AND ownedby=$uid");
	$posts = array();
	while($post=mysqli_fetch_assoc($q))
		$posts[] = $post['no'];
	return $posts;
}

function iplog_add($board, $no, $ip) {
	$board = mysqli_real_escape_string($board);
	$no = (int)$no;
	$ip = mysqli_real_escape_string($ip);
	mysqli_board_call($con, "INSERT INTO iplog (board,no,ip) VALUES ('$board',$no,'$ip')");
}
// build a structure out of all the posts in the database.
// this lets us replace a LOT of queries with a simple array access.
// it only builds the first time it was called.
// rather than calling log_cache(1) to rebuild everything,
// you should just manipulate the structure directly.
function log_cache($invalidate=0) {
	global $log, $ipcount, $mysqli_unbuffered_reads, $lastno;
    global $con;
	$ips = array();
	$threads = array(); // no's
	if($invalidate==0 && isset($log)) return;
	$log = array(); // no -> [ data ]
	mysqli_board_call($con, "SET read_buffer_size=1048576");
	$mysqli_unbuffered_reads = 1;
	$query = mysqli_board_call($con, "SELECT * FROM ".SQLLOG);
	$offset = 0;
	$lastno = 0;
	while($row = mysqli_fetch_assoc($query)) {
		if($row['no'] > $lastno) $lastno = $row['no'];
		$ips[$row['host']] = 1;
		// initialize log row if necessary
		if( !isset($log[$row['no']]) ) { 
			$log[$row['no']] = $row;
			$log[$row['no']]['children'] = array();
		} else { // otherwise merge it with $row
			foreach($row as $key=>$val)
				$log[$row['no']][$key] = $val;
		}
		// if this is a reply
		if($row['resto']) {
			// initialize whatever we need to
			if( !isset($log[$row['resto']]) ) 
				$log[$row['resto']] = array();
			if( !isset($log[$row['resto']]['children']) )
				$log[$row['resto']]['children'] = array();
				
			// add this post to list of children
			$log[$row['resto']]['children'][$row['no']] = 1;
			if($row['fsize']) {
				if(!isset($log[$row['resto']]['imgreplycount']))
					$log[$row['resto']]['imgreplycount'] = 0;
				else
					$log[$row['resto']]['imgreplycount']++;
			}
		} else {
			$threads[] = $row['no'];
		}
	}
	
	$query = mysqli_board_call($con, "SELECT no FROM ".SQLLOG." WHERE root>0 order by root desc");
	while($row = mysqli_fetch_assoc($query)) {
		if(isset($log[$row['no']]) && $log[$row['no']]['resto']==0)
			$threads[] = $row['no'];
	}
	$log['THREADS'] = $threads;
	$mysqli_unbuffered_reads = 0;
	
	// calculate old-status for PAGE_MAX mode
	if(EXPIRE_NEGLECTED != 1) {
		rsort($threads, SORT_NUMERIC);
	
		$threadcount = count($threads);
		if(PAGE_MAX > 0) // the lowest 5% of maximum threads get marked old
			for($i = floor(0.95*PAGE_MAX*PAGE_DEF); $i < $threadcount; $i++) {
				if(!$log[$threads[$i]]['sticky'] && EXPIRE_NEGLECTED != 1)
					$log[$threads[$i]]['old'] = 1;
			}
		else { // threads w/numbers below 5% of LOG_MAX get marked old
			foreach($threads as $thread) {
				if($lastno-LOG_MAX*0.95>$thread)
					if(!$log[$thread]['sticky'])
						$log[$thread]['old'] = 1;				
			}
		}
	}
	
	$ipcount = count($ips);
}


// deletes a post from the database
// imgonly: whether to just delete the file or to delete from the database as well
// automatic: always delete regardless of password/admin (for self-pruning)
// children: whether to delete just the parent post of a thread or also delete the children
// die: whether to die on error
// careful, setting children to 0 could leave orphaned posts.
function delete_post($resno, $pwd, $imgonly=0, $automatic=0, $children=1, $die=1) {
	global $log, $path;
    global $con;
	log_cache();
	$resno = intval($resno);

	// get post info
	if(!isset($log[$resno])){if ($die) error("Can't find the post $resno.");}
	$row=$log[$resno];
	
	// check password- if not ok, check admin status (and set $admindel if allowed)
	$delete_ok = ( $automatic || (substr(md5($pwd),2,8) == $row['pwd']) || ($row['host'] == $_SERVER['REMOTE_ADDR']) || ADMIN_PASS == $pwd );
	//if( ($pwd==ADMIN_PASS || $pwd==ADMIN_PASS2) ) { $delete_ok = $admindel = valid('delete', $resno); }
	if(!$delete_ok) error(S_BADDELPASS);

	// check ghost bumping
	if(!isset($admindel) || !$admindel) {
		if(BOARD_DIR == 'a' && (int)$row['time'] > (time() - 25) && $row['email'] != 'sage') {
			$ghostdump = var_export(array(
				'server'=>$_SERVER,
				'post'=>$_POST,
				'cookie'=>$_COOKIE,
				'row'=>$row),true);
			//file_put_contents('ghostbump.'.time(),$ghostdump);
		}
	}

	if(isset($admindel) && $admindel) { // extra actions for admin user
		$auser = mysqli_escape_string($_COOKIE['4chan_auser']);
		$adfsize=($row['fsize']>0)?1:0;
		$adname=str_replace('</span> <span class="postertrip">!','#',$row['name']);
		if($imgonly) { $imgonly=1; } else { $imgonly=0; }
		$row['sub'] = mysqli_escape_string($row['sub']);
		$row['com'] = mysqli_escape_string($row['com']);
		$row['filename'] = mysqli_escape_string($row['filename']);
		mysqli_board_call($con, "INSERT INTO ".SQLLOGDEL." (imgonly,postno,board,name,sub,com,img,filename,admin) values('$imgonly','$resno','".SQLLOG."','$adname','{$row['sub']}','{$row['com']}','$adfsize','{$row['filename']}','$auser')");	
	}
	
	if($row['resto']==0 && $children && !$imgonly) // select thread and children
		$result=mysqli_board_call($con, "select no,resto,tim,ext from ".SQLLOG." where no=$resno or resto=$resno");
	else // just select the post
		$result=mysqli_board_call($con, "select no,resto,tim,ext from ".SQLLOG." where no=$resno");
	
	while($delrow=mysqli_fetch_array($result)) {
		// delete
		$delfile = $path.$delrow['tim'].$delrow['ext'];	//path to delete
		$delthumb = THUMB_DIR.$delrow['tim'].'s.jpg';
		if(is_file($delfile)) unlink($delfile); // delete image
		if(is_file($delthumb)) unlink($delthumb); // delete thumb
		if(OEKAKI_BOARD == 1 && is_file($path.$delrow['tim'].'.pch')) 
			unlink($path.$delrow['tim'].'.pch'); // delete oe animation
		if(!$imgonly){ // delete thread page & log_cache row
			if($delrow['resto'])
				unset( $log[$delrow['resto']]['children'][$delrow['no']] );
			unset( $log[$delrow['no']] );
			$log['THREADS'] = array_diff($log['THREADS'], array($delrow['no'])); // remove from THREADS
			//mysqli_board_call($con, "DELETE FROM reports WHERE no=".$delrow['no']); // clear reports
			if(USE_GZIP == 1) {
				@unlink(RES_DIR.$delrow['no'].PHP_EXT);
				@unlink(RES_DIR.$delrow['no'].PHP_EXT.'.gz');
			}
			else {
				@unlink(RES_DIR.$delrow['no'].PHP_EXT);
			}
		}
	}

	//delete from DB
	if($row['resto']==0 && $children && !$imgonly) // delete thread and children
		$result=mysqli_board_call($con, "delete from ".SQLLOG." where no=$resno or resto=$resno");
	elseif(!$imgonly) // just delete the post
		$result=mysqli_board_call($con, "delete from ".SQLLOG." where no=$resno");
			
	return $row['resto']; // so the caller can know what pages need to be rebuilt
}

// purge old posts
// should be called whenever a new post is added.
function trim_db() {
    global $con;
	if(JANITOR_BOARD == 1) return;
	
	log_cache();
	
	$maxposts = LOG_MAX;
	// max threads = max pages times threads-per-page
	$maxthreads = (PAGE_MAX > 0)?(PAGE_MAX * PAGE_DEF):0;
	
	// New max-page method
	if($maxthreads) {
		$exp_order = 'no';
		if(EXPIRE_NEGLECTED == 1) $exp_order = 'root';
		//logtime('trim_db before select threads');
		$result = mysqli_board_call($con, "SELECT no FROM ".SQLLOG." WHERE sticky=0 AND resto=0 ORDER BY $exp_order ASC");
		//logtime('trim_db after select threads');
		$threadcount = mysqli_num_rows($result);
		while($row=mysqli_fetch_array($result) and $threadcount >= $maxthreads) {
			delete_post($row['no'], 'trim', 0, 1); // imgonly=0, automatic=1, children=1
			$threadcount--;
		}
		mysqli_free_result($result);
	// Original max-posts method (note: cleans orphaned posts later than parent posts)
	} else {
		// make list of stickies
		$stickies = array(); // keys are stickied thread numbers
		$result = mysqli_board_call($con, "SELECT no from ".SQLLOG." where sticky=1 and resto=0");
		while($row=mysqli_fetch_array($result)) {
			$stickies[ $row['no'] ] = 1;
		}
		
		$result = mysqli_board_call($con, "SELECT no,resto,sticky FROM ".SQLLOG." ORDER BY no ASC");
		$postcount = mysqli_num_rows($result);
		while($row=mysqli_fetch_array($result) and $postcount >= $maxposts) {
			// don't delete if this is a sticky thread
			if($row['sticky'] == 1) continue; 
			// don't delete if this is a REPLY to a sticky
			if($row['resto'] != 0 && $stickies[ $row['resto'] ] == 1) continue; 
			delete_post($row['no'], 'trim', 0, 1, 0); // imgonly=0, automatic=1, children=0
			$postcount--;
		}
		mysqli_free_result($result);
	}

}

//resno - thread page to update (no of thread OP)
//rebuild - don't rebuild page indexes
function updatelog($resno=0,$rebuild=0){
  global $log,$path;
  global $con;
	set_time_limit(60);
if($_SERVER['REQUEST_METHOD']=='GET' && !valid()) die(''); // anti ddos
	log_cache();
	
	  $imgdir = ((USE_SRC_CGI==1)?str_replace('src','src.cgi',IMG_DIR2):IMG_DIR2);
	if(defined('INTERSTITIAL_LINK')) $imgdir .= INTERSTITIAL_LINK;
	  $thumbdir = THUMB_DIR2;
		$imgurl = DATA_SERVER;


  $resno=(int)$resno;
  if($resno){
   	if(!isset($log[$resno])) {
   		updatelog(0,$rebuild); // the post didn't exist, just rebuild the indexes
   		return;
   	}
   	else if($log[$resno]['resto']) {
			updatelog($log[$resno]['resto'],$rebuild); // $resno is a reply, try rebuilding the parent
			return;
		}
  }
  
  if($resno){
  	$treeline = array($resno); //logtime("Formatting thread page");
    if(!$treeline=mysqli_board_call($con, "select * from ".SQLLOG." where root>0 and no=".$resno." order by root desc")){echo S_SQLFAIL;}
  }else{
  	$treeline = $log['THREADS']; //logtime("Formatting index page");
    if(!$treeline=mysqli_board_call($con, "select * from ".SQLLOG." where root>0 order by root desc")){echo S_SQLFAIL;}
  }


  //$counttree=count($treeline);
  $counttree=mysqli_num_rows($treeline);
  if(!$counttree){
    $logfilename=PHP_SELF2;
    $dat='';
    head($dat,$resno);
    form($dat,$resno);
    print_page($logfilename, $dat);
  }
  
	if(UPDATE_THROTTLING >= 1) {
	  $update_start = time();
	  touch("updatelog.stamp", $update_start);
	  $low_priority = false;
	  clearstatcache();
	  if(@filemtime(PHP_SELF) > $update_start-UPDATE_THROTTLING) {
	  	$low_priority = true;
	  	//touch($update_start . ".lowprio");
	  }
	  else {
	  	touch(PHP_SELF,$update_start);
	  }
	 // 	$mt = @filemtime(PHP_SELF);
	//  	touch($update_start . ".$mt.highprio");
	}
	
	// if we're using CACHE_TTL method
	if(CACHE_TTL >= 1) {
		if($resno) {
			$logfilename = RES_DIR.$resno.PHP_EXT;
		}
		else {
			$logfilename = PHP_SELF2;
		}
		//if(USE_GZIP == 1) $logfilename .= '.html';
		// if the file has been made and it's younger than CACHE_TTL seconds ago
		clearstatcache();
		if(file_exists($logfilename) && filemtime($logfilename) > (time() - CACHE_TTL)) {
			// save the post to be rebuilt later
			rebuildqueue_add($resno);
			// if it's a thread, try again on the indexes
			if($resno && !$rebuild) updatelog();
			// and we don't do any more rebuilding on this request
			return true;
		}
		else {
			// we're gonna update it now, so take it out of the queue
			rebuildqueue_remove($resno);
			// and make sure nobody else starts trying to update it because it's too old
			touch($logfilename);
		}			
	}
  
  for($page=0;$page<$counttree;$page+=PAGE_DEF){
    $dat='';
    head($dat,$resno);
    form($dat,$resno);
    if(!$resno){
      $st = $page;
    }
    $dat.='<form name="delform" action="';
    $dat.=PHP_SELF_ABS.'" method=POST>';

  for($i = $st; $i < $st+PAGE_DEF; $i++){
  	if(UPDATE_THROTTLING >= 1) {
	  	clearstatcache();
  		if($low_priority && @filemtime("updatelog.stamp") > $update_start) {
  				//touch($update_start . ".throttled");
	  			return;
	  	}
		if(rand(0,15)==0) return;
	 }
  	
  	//list($_unused,$no) = each($treeline);
    list($no,$sticky,$permasage,$closed,$now,$name,$email,$sub,$com,$host,$pwd,$filename,$ext,$w,$h,$tn_w,$tn_h,$tim,$time,$md5,$fsize,$root,$resto)=mysqli_fetch_row($treeline);
    if(!$no){break;}
    extract($log[$no]);
    //if(!$resno&&!file_exists(RES_DIR.$no.PHP_EXT)) { updatelog($no); break; } // uhh

	//POST FILTERING
	if(JANITOR_BOARD == 1) {
		$name = broomcloset_capcode($name);
	}
        if($email) $name = "<a href=\"mailto:$email\" class=\"linkmail\">$name</a>";
    if(strpos($sub,"SPOILER<>")===0) {
    	$sub = substr($sub,strlen("SPOILER<>")); //trim out SPOILER<>
    	$spoiler = 1;
    } else $spoiler = 0;
    $com = auto_link($com,$resno);
    if(MAKE_AMERICAN == 1) {
        $com = make_american($com);
    }
    if(!$resno) list($com,$abbreviated) = abbreviate($com, MAX_LINES_SHOWN);

	  if(isset($abbreviated) && $abbreviated) $com .= "<br /><span class=\"abbr\">Comment too long. Click <a href=\"".RES_DIR.($resto?$resto:$no).PHP_EXT."#$no\">here</a> to view the full text.</span>";
    // Picture file name
    $img = $path.$tim.$ext;
    $displaysrc = $imgdir.$tim.$ext;
    $linksrc = ((USE_SRC_CGI==1)?(str_replace(".cgi","",$imgdir).$tim.$ext):$displaysrc);
	if(defined('INTERSTITIAL_LINK')) $linksrc = str_replace(INTERSTITIAL_LINK,"",$linksrc);
    $src = IMG_DIR.$tim.$ext;
    $longname = $filename.$ext;
    if (strlen($filename)>40) {
	    $shortname = substr($filename, 0, 40)."(...)".$ext;
    } else {
	    $shortname = $longname;
    }
    // img tag creation
    $imgsrc = "";
    if($ext){
    	// turn the 32-byte ascii md5 into a 24-byte base64 md5
    	$shortmd5 = base64_encode(pack("H*", $md5));
      if ($fsize >= 1048576) { $size = round(($fsize/1048576),2)." M";
      } else if ($fsize >= 1024) { $size = round($fsize/1024)." K";
      } else { $size = $fsize." "; }
      if(!$tn_w && !$tn_h && $ext==".gif"){
      	$tn_w=$w;
        $tn_h=$h;
      }
      	          if($spoiler) {
	          $size= "Spoiler Image, $size"; 
	              $imgsrc = "<br><a href=\"".$displaysrc."\" target=_blank><img src=\"".SPOILER_THUMB."\" border=0 align=left hspace=20 alt=\"".$size."B\" md5=\"$shortmd5\"></a>";	          
	          } elseif($tn_w && $tn_h){//when there is size...
        if(@is_file(THUMB_DIR.$tim.'s.jpg')){
          $imgsrc = "<br><a href=\"".$displaysrc."\" target=_blank><img src=".$thumbdir.$tim.'s.jpg'." border=0 align=left width=$tn_w height=$tn_h hspace=20 alt=\"".$size."B\" md5=\"$shortmd5\"></a>";
        }else{
          $imgsrc = "<a href=\"".$displaysrc."\" target=_blank><span class=\"tn_thread\" title=\"".$size."B\">Thumbnail unavailable</span></a>";
        }
      }else{
        if(@is_file(THUMB_DIR.$tim.'s.jpg')){
          $imgsrc = "<br><a href=\"".$displaysrc."\" target=_blank><img src=".$thumbdir.$tim.'s.jpg'." border=0 align=left hspace=20 alt=\"".$size."B\" md5=\"$shortmd5\"></a>";
        }else{
          $imgsrc = "<a href=\"".$displaysrc."\" target=_blank><span class=\"tn_thread\" title=\"".$size."B\">Thumbnail unavailable</span></a>";
        }
      }
      if (!is_file($src)) {
	      $dat.='<img src="'.$imgurl.'filedeleted.gif" alt="File deleted.">';
      } else {
        $dimensions=($ext=='.pdf')?'PDF':"{$w}x{$h}";
      	if ($resno) {
   	      $dat.="<span class=\"filesize\">".S_PICNAME."<a href=\"$linksrc\" target=\"_blank\">$time$ext</a>-(".$size."B, ".$dimensions.", <span title=\"".$longname."\">".$shortname."</span>)</span>".$imgsrc;
				} else {
		      $dat.="<span class=\"filesize\">".S_PICNAME."<a href=\"$linksrc\" target=\"_blank\">$time$ext</a>-(".$size."B, ".$dimensions.")</span>".$imgsrc;
        }
      }
    }
    //  Main creation
    $dat.="<a name=\"$resno\"></a>\n<input type=checkbox name=\"$no\" value=delete><span class=\"filetitle\">$sub</span> \n";
    $dat.="<span class=\"postername\">$name</span> $now <span id=\"nothread$no\">";

    if($sticky==1) {
    	$stickyicon=' <img src="'.$imgurl.'sticky.gif" alt="sticky"> ';
    } else { $stickyicon=""; }
    if($closed==1) {
    	$stickyicon .= ' <img src="'.$imgurl.'closed.gif" alt="closed"> ';
    }
    
    if(PARTY==1) {
    	$dat .= "<img src='https://s.4cdn.org/image/partyhat.gif' style='position:absolute;margin-top:-100px;left:0px;'>";
    }
    	
    if($resno) {
    	$dat.="<a href=\"#$no\" class=\"quotejs\">No.</a><a href=\"javascript:quote('$no')\" class=\"quotejs\">$no</a> $stickyicon &nbsp; ";
    } else {
    	$dat.="<a href=\"".RES_DIR.$no.PHP_EXT."#".$no."\" class=\"quotejs\">No.</a><a href=\"".RES_DIR.$no.PHP_EXT."#q".$no."\" class=\"quotejs\">$no</a> $stickyicon &nbsp; [<a href=\"".RES_DIR.$no.PHP_EXT."\">".S_REPLY."</a>]";
    }
    $dat.="</span>\n<blockquote>$com</blockquote>";

     // Deletion pending
      if(isset($log[$no]['old'])) $dat.="<span class=\"oldpost\">".S_OLD."</span><br>\n";

    $resline = $log[$no]['children'];
    ksort($resline);
    $countres=count($log[$no]['children']);
    $t=0;
    if($sticky==1) {
	    $disam=1;
    } elseif(defined('REPLIES_SHOWN')) {
    	$disam=REPLIES_SHOWN;
    } else {
    	$disam=5;
    }
    $s=$countres - $disam;
	  $cur=1;
	  while ($s >= $cur) {
	    list($row) = each($resline);
      if($log[$row]["fsize"]!=0) { $t++; }
	    $cur++;
		}
    if ($countres!=0) reset($resline);

    if(!$resno){
    if ($s<2) { $posts=" post"; } else { $posts=" posts"; }
    if ($t<2) { $replies="reply"; } else { $replies="replies"; }
     if(($s>0)&&($t==0)){
      $dat.="<span class=\"omittedposts\">".$s.$posts." omitted. Click Reply to view.</span>\n";
     } elseif (($s>0)&&($t>0)) {
      $dat.="<span class=\"omittedposts\">".$s.$posts." and ".$t." image ".$replies." omitted. Click Reply to view.</span>\n";
     }
    }else{$s=0;}
	
    while(list($resrow)=each($resline)){
      if($s>0){$s--;continue;}
      //list($no,$sticky,$permasage,$closed,$now,$name,$email,$sub,$com,$host,$pwd,$filename,$ext,$w,$h,$tn_w,$tn_h,$tim,$time,$md5,$fsize,$root,$resto)=$resrow;
      extract($log[$resrow]);
      if(!$no){break;}

	//POST FILTERING
	if(JANITOR_BOARD == 1) {
		$name = broomcloset_capcode($name);
	}
        if($email) $name = "<a href=\"mailto:$email\" class=\"linkmail\">$name</a>";
    if(strpos($sub,"SPOILER<>")===0) {
    	$sub = substr($sub,strlen("SPOILER<>")); //trim out SPOILER<>
    	$spoiler = 1;
    } else $spoiler = 0;
    $com = auto_link($com,$resno);
    if(MAKE_AMERICAN == 1) {
        $com = make_american($com);
    }
    if(!$resno) list($com,$abbreviated) = abbreviate($com, MAX_LINES_SHOWN);

	  if(isset($abbreviated)&&$abbreviated) $com .= "<br /><span class=\"abbr\">Comment too long. Click <a href=\"".RES_DIR.($resto?$resto:$no).PHP_EXT."#$no\">here</a> to view the full text.</span>";
	  
	        // Picture file name
	        $r_img = $path.$tim.$ext;
	        $r_displaysrc = $imgdir.$tim.$ext;
            $r_linksrc = ((USE_SRC_CGI==1)?(str_replace(".cgi","",$imgdir).$tim.$ext):$r_displaysrc);
	if(defined('INTERSTITIAL_LINK')) $r_linksrc = str_replace(INTERSTITIAL_LINK,"",$r_linksrc);
	        $r_src = IMG_DIR.$tim.$ext;
	        $longname = $filename.$ext;
	        if (strlen($filename)>30) {
	          $shortname = substr($filename, 0, 30)."(...)".$ext;
	        } else {
	          $shortname = $longname;
	        }
	        // img tag creation
	        $r_imgsrc = "";
	        if($ext){
	            	// turn the 32-byte ascii md5 into a 24-byte base64 md5
    		$shortmd5 = base64_encode(pack("H*", $md5));
		  if ($fsize >= 1048576) { $size = round(($fsize/1048576),2)." M";
		  } else if ($fsize >= 1024) { $size = round($fsize/1024)." K";
		  } else { $size = $fsize." "; }
	          if(!$tn_w && !$tn_h && $ext==".gif"){
	            $tn_w=$w;
	            $tn_h=$h;
	          }
	          if($spoiler) {
	          $size= "Spoiler Image, $size"; 
	              $r_imgsrc = "<br><a href=\"".$r_displaysrc."\" target=_blank><img src=\"".SPOILER_THUMB."\" border=0 align=left hspace=20 alt=\"".$size."B\" md5=\"$shortmd5\"></a>";	          
	          }
	          elseif($tn_w && $tn_h){//when there is size...
	            if(@is_file(THUMB_DIR.$tim.'s.jpg')){
	              $r_imgsrc = "<br><a href=\"".$r_displaysrc."\" target=_blank><img src=".$thumbdir.$tim.'s.jpg'." border=0 align=left width=$tn_w height=$tn_h hspace=20 alt=\"".$size."B\" md5=\"$shortmd5\"></a>";
	            }else{
	              $r_imgsrc = "<a href=\"".$r_displaysrc."\" target=_blank><span class=\"tn_reply\" title=\"".$size."B\">Thumbnail unavailable</span></a>";
	            }
	          }else{
	            if(@is_file(THUMB_DIR.$tim.'s.jpg')){
	              $r_imgsrc = "<br><a href=\"".$r_displaysrc."\" target=_blank><img src=".$thumbdir.$tim.'s.jpg'." border=0 align=left hspace=20 alt=\"".$size."B\" md5=\"$shortmd5\"></a>";
	            }else{
	              $r_imgsrc = "<a href=\"".$r_displaysrc."\" target=_blank><span class=\"tn_reply\" title=\"".$size."B\">Thumbnail unavailable</span></a>";
	            }
	          }
	          if (!is_file($r_src)) {
	            $r_imgreply='<br><img src="'.$imgurl.'filedeleted-res.gif" alt="File deleted.">';
	          } else {
	          	$dimensions=($ext=='.pdf')?'PDF':"{$w}x{$h}";
            	if ($resno) {
		            $r_imgreply="<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class=\"filesize\">".S_PICNAME."<a href=\"$r_linksrc\" target=\"_blank\">$time$ext</a>-(".$size."B, ".$dimensions.", <span title=\"".$longname."\">".$shortname."</span>)</span>".$r_imgsrc;
							} else {
		            $r_imgreply="<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class=\"filesize\">".S_PICNAME."<a href=\"$r_linksrc\" target=\"_blank\">$time$ext</a>-(".$size."B, ".$dimensions.")</span>".$r_imgsrc;
              }
	          }
	        }
	        
      // Main Reply creation
      $dat.="<a name=\"$no\"></a>\n";
          	$dat.="<table><tr><td nowrap class=\"doubledash\">&gt;&gt;</td><td id=\"$no\" class=\"reply\">\n";
//      if (($t>3)&&($fsize!=0)) {
//      $dat.="&nbsp;&nbsp;&nbsp;<b>Image hidden</b>&nbsp;&nbsp; $now No.$no \n";
//			} else {
      $dat.="<input type=checkbox name=\"$no\" value=delete><span class=\"replytitle\">$sub</span> \n";
      $dat.="<span class=\"commentpostername\">$name</span> $now <span id=\"norep$no\">";
      if($resno) {
        $dat.="<a href=\"#$no\" class=\"quotejs\">No.</a><a href=\"javascript:quote('$no')\" class=\"quotejs\">$no</a></span>";
      } else {
        $dat.="<a href=\"".RES_DIR.$resto.PHP_EXT."#$no\" class=\"quotejs\">No.</a><a href=\"".RES_DIR.$resto.PHP_EXT."#q$no\" class=\"quotejs\">$no</a></span>";
      }
      if(isset($r_imgreply)) $dat.=$r_imgreply; 
      $dat.="<blockquote>$com</blockquote>";
//      }
      $dat.="</td></tr></table>\n";
    	unset($r_imgreply);
    }
    $dat.="<br clear=left><hr>\n";
    clearstatcache();//clear stat cache of a file
    //mysqli_free_result($resline);
	$p++;
    if($resno){break;} //only one tree line at time of res
  }
	// bottom of a page
	if(BOTTOM_AD == 1) {
		$bottomad = "";
		
		if (defined("BOTTOM_TXT") && BOTTOM_TXT) {
			$bottomad .= ad_text_for(BOTTOM_TXT);
		}
		
		if (defined("BOTTOM_TABLE") && BOTTOM_TABLE) {
			list($bottomimg,$bottomlink) = rid(BOTTOM_TABLE,1);
			$bottomad .= "<center><a href=\"$bottomlink\" target=\"_blank\"><img style=\"border:1px solid black;\" src=\"$bottomimg\" width=728 height=90 border=0 /></a></center>";
		}
		
		if($bottomad)
			$dat .= "$bottomad<hr>";
	}
		
$dat.='<table align=right><tr><td nowrap align=center class=deletebuttons>
<input type=hidden name=mode value=usrdel>'.S_REPDEL.' [<input class=checkbox type=checkbox name=onlyimgdel value=on>'.S_DELPICONLY.']<br>
'.S_DELKEY.' <input class=inputtext type=password name="pwd" size=8 maxlength=8 value="">
<input type=submit value="'.S_DELETE.'"><input type="button" value="Report" onclick="var o=document.getElementsByTagName(\'INPUT\');for(var i=0;i<o.length;i++)if(o[i].type==\'checkbox\' && o[i].checked && o[i].value==\'delete\') return reppop(\''.PHP_SELF_ABS.'?mode=report&no=\'+o[i].name+\'\');"></form><script>document.delform.pwd.value=get_pass("4chan_pass");</script></td></tr>';
if (strpos($_SERVER['SERVER_NAME'],".example.com")) {
	$dat.='<tr><td align="right">Style [';
	$dat.='<a href="#" onclick="setActiveStyleSheet(\'Yotsuba\'); return false;">Yotsuba</a> | ';
	$dat.='<a href="#" onclick="setActiveStyleSheet(\'Yotsuba B\'); return false;">Yotsuba B</a> | ';
	$dat.='<a href="#" onclick="setActiveStyleSheet(\'Futaba\'); return false;">Futaba</a> | ';
	$dat.='<a href="#" onclick="setActiveStyleSheet(\'Burichan\'); return false;">Burichan</a>]</td></tr>';
}
$dat.='</table>';

    if(!$resno){ // if not in res display mode
      $prev = $st - PAGE_DEF;
      $next = $st + PAGE_DEF;
    // Page navigation
      $dat.="<table class=pages align=left border=1><tr>";
      if($prev >= 0){ //ok to make prev button
        if($prev==0){
          $dat.="<form action=\"".PHP_SELF2."\" onsubmit='location=this.action;return false;' method=get><td>";
        }else{
          $dat.="<form action=\"".$prev/PAGE_DEF.PHP_EXT."\" onsubmit='location=this.action;return false;' method=get><td>";
        }
        $dat.="<input type=submit value=\"".S_PREV."\" accesskey=\"z\">";
        $dat.="</td></form>";
      }else{$dat.="<td>".S_FIRSTPG."</td>";}
		// page listing
      $dat.="<td>";
      for($i = 0; $i < $counttree ; $i+=PAGE_DEF){
      	if( !(PAGE_MAX > 0) )
        	if($i&&!($i%(PAGE_DEF*10))){$dat.="<br>";} // linebreak every 10 pages
        if($st==$i){$dat.="[<b>".($i/PAGE_DEF)."</b>] ";} // don't link current page
        else{
          if($i==0){$dat.="[<a href=\"".PHP_SELF2."\">0</a>] ";}
          else{$dat.="[<a href=\"".($i/PAGE_DEF).PHP_EXT."\">".($i/PAGE_DEF)."</a>] ";}
        }
      }
      // continue printing up to PAGE_MAX if we're using that mode... this should rarely happen
      for(; (PAGE_MAX > 0) && $i < PAGE_MAX*PAGE_DEF; $i+=PAGE_DEF) {
      	$dat.="[".($i/PAGE_DEF)."] ";
      }
      
      $dat.="</td>";

      if($p >= PAGE_DEF && $counttree > $next){ // ok to make next button
        $dat.="<form action=\"".$next/PAGE_DEF.PHP_EXT."\" onsubmit='location=this.action;return false' method=get><td>";
        $dat.="<input type=submit value=\"".S_NEXT."\" accesskey=\"x\">";
        $dat.="</td></form>";
      }else{$dat.="<td>".S_LASTPG."</td>";}
        $dat.="</tr></table><br clear=all>\n";
    }
    foot($dat);
    //if($resno){echo $dat;break;}
    if($resno) {
    	//logtime("Printing thread $resno page");
	    $logfilename=RES_DIR.$resno.PHP_EXT;
	    print_page($logfilename, $dat);
		$dat='';
      if(!$rebuild) $deferred = updatelog(0);
      break;
    }
    //logtime("Printing index page");
    if($page==0){$logfilename=PHP_SELF2;}
    else{$logfilename=$page/PAGE_DEF.PHP_EXT;}
    print_page($logfilename, $dat);
    if(!$resno && $page==0 && USE_RSS==1) {
    	include_once '/www/global/rss.php';
    	rss_dump();
    }
    if(UPDATE_THROTTLING >= 1) {
	  	clearstatcache();
  		if(@filemtime("updatelog.stamp") == $update_start)
  			unlink("updatelog.stamp");
    }
    //chmod($logfilename,0666);
  }
  mysqli_free_result($treeline);
  if(isset($deferred)) return $deferred;
  return false;
}

function mysqli_board_call($link, $query){
    $ret=mysqli_query($link, $query);
    if(!$ret){
  #echo "error!!<br />";
      echo $query."<br />";
  #    echo mysqli_errno().": ".mysqli_error()."<br />";
    }
    return $ret;
  }

/* head */
function head(&$dat,$res,$error=0){
	$titlepart = '';
	if(JANITOR_BOARD == 1) {
		$dat .= broomcloset_head($dat);
	}

  if (SHOWTITLEIMG == 1) {
  		//$titleimg = rid('title_banners');
	  $titleimg = rid_in_directory("/dontblockthis/title/");
	  $titlepart.= '<img width=300 height=100 src="'.$titleimg.'">';
	} else if (SHOWTITLEIMG == 2) {
	  $titlepart.= '<img width=300 height=100 src="'.TITLEIMG.'" onclick="this.src=this.src;">';
	}
  //$include1=file_get_contents_cached(NAV_TXT);
  $cookiejs="function get_cookie(name){with(document.cookie){var index=indexOf(name+\"=\");if(index==-1) return '';index=indexOf(\"=\",index)+1;var endstr=indexOf(\";\",index);if(endstr==-1) endstr=length;return decodeURIComponent(substring(index,endstr));}};\nfunction get_pass(name){var pass=get_cookie(name);if(pass) return pass;var chars=\"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789\";var pass='';for(var i=0;i<8;i++){var rnd=Math.floor(Math.random()*chars.length);pass+=chars.substring(rnd,rnd+1);}return(pass);}\n";
  $cookiejs .= 'function toggle(name){var a=document.getElementById(name); a.style.display = ((a.style.display!="block")?"block":"none");}';
  $scriptjs = '';
  // set styleswitcher script configuration variables
  if(DEFAULT_BURICHAN==1) {
  	$scriptjs .= '<script type="text/javascript">var style_group="ws_style";</script>';
  } else {
  	$scriptjs .= '<script type="text/javascript">var style_group="nws_style";</script>';
  }
	$scriptjs .='<script type="text/javascript" src="'.DATA_SERVER.'script.js"></script>';
  $dat.='<html><head>
<META HTTP-EQUIV="content-type" CONTENT="text/html; charset=UTF-8">
<meta name="robots" content="'.META_ROBOTS.'"/>
<meta name="description" content="'.META_DESCRIPTION.'"/>
<meta name="keywords" content="'.META_KEYWORDS.'"/>';
	if (RTA==1) {
		$dat .= "\n<meta name=\"RATING\" content=\"RTA-5042-1996-1400-1577-RTA\" />";
	}
$styles = array( 
	'Yotsuba' => 'yotsuba.8.css', 
	'Yotsuba B' => 'yotsublue.8.css', 
	'Futaba' => 'futaba.8.css',
	'Burichan' => 'burichan.8.css',
);
	if (DEFAULT_BURICHAN==1) {
		foreach($styles as $style=>$stylecss) {
			$rel = ( ($style == 'Yotsuba B') ? 'stylesheet' : 'alternate stylesheet' );
			$dat .= "<link rel=\"$rel\" type=\"text/css\" href=\"".DATA_SERVER."$stylecss\" title=\"$style\">";
		}
	} else {
		if(defined('CSS_FORCE')) { 
			foreach($styles as $style=>$stylecss) {
				$rel = ( ($style == 'Yotsuba') ? 'stylesheet' : 'alternate stylesheet' );
				$dat .= "<link rel=\"$rel\" type=\"text/css\" href=\"".CSS_FORCE."\" title=\"$style\">";
			}
		}
		else {
			foreach($styles as $style=>$stylecss) {
				$rel = ( ($style == 'Yotsuba') ? 'stylesheet' : 'alternate stylesheet' );
				$dat .= "<link rel=\"$rel\" type=\"text/css\" href=\"".DATA_SERVER."$stylecss\" title=\"$style\">";
			}
		}
	}

if(USE_RSS==1)
	$dat .= '<link rel="alternate" title="RSS feed" href="/'.BOARD_DIR.'/index.rss" type="application/rss+xml" />';
$dat.='<title>'.strip_tags(TITLE).'</title>
<script type="text/javascript"><!--
'.$cookiejs.'
//--></script>
'.$scriptjs;

if (FIXED_TEXT_AD == 1 && file_exists(FIXED_TEXT_PATH)) {
	$dat.="<style>.postarea { padding-left:400px; }</style>";
}

$dat .= '</head>
<body bgcolor="#FFFFEE" text="#800000" link="#0000EE" vlink="#0000EE">';//.$include1;

$dat.='<div class="logo">
'.$titlepart.'<br>
<font size=5>
<b><SPAN>'.TITLE.'</SPAN></b></font>';
if(defined('SUBTITLE'))
	$dat .= '<br><font size=1>'.SUBTITLE.'</font>';
$dat.='</div>
<hr width="90%" size=1>
';
if(LEADERBOARD_AD == 1) {
		if(defined('LEADERBOARD_TXT') && LEADERBOARD_TXT) {
			$dat.='<div style="text-align: center">' .
				ad_text_for(LEADERBOARD_TXT) .
			'</div><hr>';
		}
		else if(defined('LEADERBOARD_TABLE')) {
			list($ldimg,$ldhref) = rid(LEADERBOARD_TABLE,1);
			$dat.='<div style="text-align: center"><a href="'.$ldhref.'" target="_blank"><img src="'.$ldimg.'" border="0"></a></div><hr>';
		}
		else
			$dat.='<div style="text-align: center"><a href="'.LEADERBOARD_LINK.'" target="_blank"><img src="http://content.4chan.org/dontblockthis/'.LEADERBOARD_IMG.'" border="0"></a></div><hr>';
}
}

/* Contribution form */
function form(&$dat,$resno,$admin=""){
  global $log; log_cache();
  global $con;
  $maxbyte = MAX_KB * 1024;
  $no=$resno;
  $closed=0;
  $msg='';
  $hidden='';
  if($resno){
    $closed = $log[$resno]['closed'];

    $msg.="[<a href=\"../".PHP_SELF2."\" accesskey=\"a\">".S_RETURN."</a>]\n";
    $msg.="<table width='100%'><tr><th bgcolor=#e04000>\n";
    $msg.="<font color=#FFFFFF>".S_POSTING."</font>\n";
    $msg.="</th></tr></table>\n";
  }
  if($admin){
    $hidden = "<input type=hidden name=admin value=\"".ADMIN_PASS."\">";
    $msg = "<h4>".S_NOTAGS."</h4>";
  }
if($closed!=1) {
  $dat.=$msg;
  //form_ads($dat);
if(OEKAKI_BOARD == 1) { 
	require_once 'oekaki.php'; 
	if($_GET['mode'] != 'oe_finish') 
		oe_form($dat,$resno);
	else
		oe_preview($dat);		
}
  $dat.='<div align="center" class="postarea"><form name="post" action="';
	$dat.=PHP_SELF_ABS.'" method="POST" enctype="multipart/form-data">
'.$hidden.'<input type=hidden name="MAX_FILE_SIZE" value="'.$maxbyte.'">
';
if($no){$dat.='<input type=hidden name=resto value="'.$no.'">
';}
	if((FIXED_TEXT_AD == 1) && $fixedad = ad_text_for(FIXED_TEXT_PATH)) {
	$dat.='<div id="ad">'.$fixedad.'</div>';
	}
if(FORCED_ANON == 1) {
	$dat.='<table cellpadding=1 cellspacing=1><tr colspan=2><td><input type=hidden name=name><input type=hidden name=sub>&nbsp;</td></tr>'
	.'<tr><td></td><td class="postblock" align="left"><b>'.S_EMAIL.'</b></td><td><input class=inputtext type=text name=email size="28"><span id="tdname"></span><span id="tdemail"></span>';
} else {
$dat.='<table cellpadding=1 cellspacing=1>
<tr><td></td><td class="postblock" align="left"><b>'.S_NAME.'</b></td><td><input class=inputtext type=text name=name size="28"><span id="tdname"></span></td></tr>
<tr><td></td><td class="postblock" align="left"><b>'.S_EMAIL.'</b></td><td><input class=inputtext type=text name=email size="28"><span id="tdemail"></span></td></tr>
<tr><td></td><td class="postblock" align="left"><b>'.S_SUBJECT.'</b></td><td><input class=inputtext type=text name=sub size="35">';
}
if($admin){
$dat.='<tr><td></td><td class="postblock" align="left"><b>Reply ID</b></td><td><input class=inputtext type=text name=resto size="10"> [<label><input type=checkbox name=age value=1>Age</label>] ';
}
$dat.='<input type=submit value="'.S_SUBMIT.'" accesskey="s">';
if (SPOILERS==1) {
  $dat.=' [<label><input type=checkbox name=spoiler value=on>'.S_SPOILERS.'</label>]';
};
$dat.='</td></tr>
<tr><td valign=bottom></td><td class="postblock" align="left"><b>'.S_COMMENT.'</b></td><td><textarea class=inputtext name=com cols="48" rows="4" wrap=soft></textarea></td></tr>
';
if(OEKAKI_BOARD == 1 && $_GET['mode'] == 'oe_finish') { require_once 'oekaki.php'; oe_finish_form($dat); }
elseif (MAX_IMGRES!=0) {
	$dat.='<tr><td></td><td class="postblock" align="left"><b>'.S_UPLOADFILE.'</b></td>
	<td><input type=file name=upfile size="35">';
	if (!$resno&&NO_TEXTONLY!=1) {
	$dat.='[<label><input type=checkbox name=textonly value=on>'.S_NOFILE.'</label>]';
	}
	$dat.='</td></tr>';
}
$dat.='<tr><td></td><td class="postblock" align="left"><b>'.S_DELPASS.'</b></td><td><input class=inputtext type=password name="pwd" size=8 maxlength=8 value=""><small>'.S_DELEXPL.'</small><input type=hidden name=mode value="regist"></td></tr>
<tr><td></td><td colspan=2>
<table border=0 cellpadding=0 cellspacing=0 width="100%"><tr><td class="rules">'.S_RULES;
if(!$resno && SHOW_UNIQUES == 1) {
  $dat.='<LI>Currently <b>'.$GLOBALS['ipcount'].'</b> unique user posts.';
}
//$dat.='</td><td align="right" valign="center">'.DONATE.'</td></tr>';
if(FORCED_ANON==1) { // extra spacer to make up for the 2 missing table rows
	$dat .='<tr><td>&nbsp;</td></tr>';
}
if(SHOW_BLOTTER == 1) {
list($blotdate,$blotcontents) = blotter_contents();
$dat.='<tr><td class="rules">
<script type="text/javascript"><!--
function updateBlotterVisible() {
	if(get_cookie("blotter_hide") == "show") {
		document.getElementById("blotter").style.display = \'inline\';
	} else {
		document.getElementById("blotter").style.display = \'none\';
	}
}

function toggleBlotter() {
	if(get_cookie("blotter_hide") == "show") {
		document.cookie = "blotter_hide=hide; expires=Thu, 4 Feb 2044 04:04:04 UTC; domain=example.com; path=/";
	} else {
		document.cookie = "blotter_hide=show; expires=Thu, 4 Feb 2044 04:04:04 UTC; domain=example.com; path=/";
	}
	updateBlotterVisible();
}
document.write(\'<div style="position:relative;"><div style="top:0px;left:0px;position:absolute;" class="rules">'.$blotdate.'</div><div style="top:0px;right:0px;position:absolute;"><a href="javascript:void(0)" onclick="toggleBlotter()">Show/Hide</a> <a href="'.BLOTTER_URL.'?all">Show All</a></div><div id="blotter" style="display:none" class="rules"><br/>\');
	document.write(' . $blotcontents . ');
document.write(\'</div></div>\');
updateBlotterVisible();
-->
</script>
</td></tr>';
}
$dat.='</table></td></tr></table></form></div><hr>
<script>with(document.post) {name.value=get_cookie("4chan_name"); email.value=get_cookie("4chan_email"); pwd.value=get_pass("4chan_pass"); }</script>
';
} else { // closed thread
	$dat.="[<a href=\"../".PHP_SELF2."\" accesskey=\"a\">".S_RETURN."</a>]<hr>\n";
	//form_ads($dat);
	$dat.='<table style="text-align:center;width:100%;height:300px;"><tr valign="middle"><td align="center"><font color=red size=5 style=""><b>Thread closed.<br/>You may not reply at this time.</b></font></tr></td></table>';
}
	if (BANROT_AD==1) {
	    /*if(!$banadquery=mysqli_board_call($con, "select url,img from ".BANROTLOG." order by rand() limit 1")){echo S_SQLFAIL;}
	    $banadrow=mysqli_fetch_row($banadquery);
	    list($ba_url,$ba_img)=$banadrow;*/
	    $dat.='<center>';
		if(defined('TOPAD_TABLE')) {
			list($topad, $toplink) = rid(TOPAD_TABLE, 1);
			$dat .= "<a href=\"$toplink\" target=\"_blank\"><img style=\"border:1px solid black;\" src=\"$topad\" width=468 height=60 border=0 /></a>";
		}
		else {
			$dat .= rotating_ad_banner();
		}
	    /*
	    $dat.='<a href="http://webhosting.cologuys.com" target="_blank"><img src="http://content.4chan.org/dontblockthis/CG_100x60_2.gif" border="0"></a>';
	    if ($ba_url != "") {
		    $dat.='<a href="'.BANROT_PHP.'?url='.$ba_url.'" target="_blank"><img src="'.$ba_img.'" border="0"></a>';
	    } else {
		    $dat.='<img src="'.$ba_img.'" border="0">';
	    }*/

	}
	
	if(BANROT2_AD==1) {
	/*	$dat .= @file_get_contents('/www/global/topad.txt');
	    $dat.='<a href="http://webhosting.cologuys.com" target="_blank"><img src="http://content.4chan.org/dontblockthis/CG_100x60_2.gif" border="0"></a>';
	    $dat.="</center><hr>\n";*/
	}
	elseif (BANROT_B==1) {
		/*if(!$banadquery=mysqli_board_call($con, "select url,img from ".BANROT_B_LOG." where DATE_SUB(CURDATE(),INTERVAL 30 DAY) <= installed) ORDER BY RAND() limit 1")){echo S_SQLFAIL;}
		$banadrow=mysqli_fetch_row($banadquery);
		list($ba_url,$ba_img)=$banadrow;
		$dat.='<br/>';
		if ($ba_url != "") {
			$dat.='<a href="'.$ba_url.'" target="_blank"><img src="'.$ba_img.'" border="0"></a>';
		} else {
			$dat.='<img src="'.$ba_img.'" border="0">';
		}
	  $dat.="<br><a href=\"http://www.4chan.org/advertise/\" target=\"_blank\"><small>Buy a banner for this board!</small></a></center><hr>\n";*/
	} 
	elseif(NOT4CHAN!=1 && BANROT_AD==1 || BANROT2_AD==1) {
  	//$dat.="<br><a href=\"http://www.4chan.org/advertise/\" target=\"_blank\"><small>Advertise with 4chan!</small></a></center><hr>\n";
  	$dat.="</center><hr>\n";
  }
  
  if (defined('GLOBAL_MSG') && GLOBAL_MSG!='') {
	  $dat.=GLOBAL_MSG."\n<hr>\n";
  }
  
  if(JANITOR_BOARD == 1) {
  	$dat = broomcloset_form($dat);
  }
}

function delete_uploaded_files()
{
	global $upfile_name,$path,$upfile,$dest;
 	if($dest||$upfile) {
	  @unlink($dest);
	  @unlink($upfile);
	  if(OEKAKI_BOARD == 1) { @unlink("$dest.pch"); }
	}
}

/* Footer */
function foot(&$dat){
 global $update_avg_secs;
 //$include2=file_get_contents_cached(NAV2_TXT);
/* $dat.='<div class="footer">'.S_FOOT.'</div>
'.$include2.'
</body></html>';*/
  //$dat .="$include2";
  if ($update_avg_secs) $dat .= "<!-- $update_avg_secs s -->";
  $dat .= "</body></html>";
}
function error($mes,$unused=''){
  delete_uploaded_files();
  head($dat,0,1);
  //form_ads($dat);
 
  //echo "<br><br><hr size=1><br><br>\n<center><font color=red size=5><b>$mes<br><br><a href=";
  $dat .= '<table style="text-align:center;width:100%;height:300px;"><tr valign="middle"><td align="center"><font color=red size=5 style=""><b>' . $mes . '<br><br><a href=';
  if(strpos($_SERVER['REQUEST_URI'],RES_DIR)) $dat .= "../";
  //echo PHP_SELF2.">".S_RELOAD."</a></b></font></center><br><br><hr size=1>";
  $dat .=  PHP_SELF2.">".S_RELOAD."</a></b></font></tr></td></table><br><br><hr size=1>";
  if(BANROT_AD == 1 && !defined('TOPAD_TABLE')) {
  	    $dat.='<center>';
		$dat .= rotating_ad_banner();
		if(BOTTOM_AD == 1) {
			$dat .= "<hr size=1>";
		}
  }
 if(BOTTOM_AD == 1) {
	$bottomad = ad_text_for(BOTTOMAD);
	if($bottomad)
		$dat .= "$bottomad<hr>";
  }
  $dat .= "</center>";
  foot($dat);
  die($dat);
}

/* Auto Linker */
function normalize_link_cb($m) {
	$subdomain = $m[1];
	$original = $m[0];
	$board = strtolower($m[2]);
	$m[0] = $m[1] = $m[2] = '';
	for($i=count($m)-1;$i>2;$i--) {
		if($m[$i]) { $no = $m[$i]; break; }
	}
	if($subdomain == 'www' || $subdomain == 'static' || $subdomain == 'content')
		return $original;
	if($board == BOARD_DIR)
		return "&gt;&gt;$no";
	else
		return "&gt;&gt;&gt;/$board/$no";
}
function normalize_links($proto) {
	// change http://xxx.4chan.org/board/res/no links into plaintext >># or >>>/board/#
	if(strpos($proto,"example.com")===FALSE) return $proto;
	
	$proto = preg_replace_callback('@http://([A-za-z]*)[.]4chan[.]org/(\w+)/(?:res/(\d+)[.]html(?:#q?(\d+))?|\w+.php[?]res=(\d+)(?:#(\d+))?|)(?=[\s.<!?,]|$)@i','normalize_link_cb',$proto);
	// rs.4chan.org to >>>rs/query+string
	$proto = preg_replace('@http://rs[.]4chan[.]org/\?s=([a-zA-Z0-9$_.+-]+)@i','&gt;&gt;&gt;/rs/$1',$proto);
	return $proto;
}

function intraboard_link_cb($m) {
	global $intraboard_cb_resno, $log;
	$no = $m[1];
	$resno = $intraboard_cb_resno;
	if(isset($log[$no])) {
		$resto = $log[$no]['resto'];
		$resdir = ($resno ? '' : RES_DIR);
		$ext = PHP_EXT;
		if($resno && $resno==$resto) // linking to a reply in the same thread
			return "<a href=\"#$no\" class=\"quotelink\" onClick=\"replyhl('$no');\">&gt;&gt;$no</a>";
		elseif($resto==0) // linking to a thread
			return "<a href=\"$resdir$no$ext#$no\" class=\"quotelink\">&gt;&gt;$no</a>";
		else // linking to a reply in another thread
			return "<a href=\"$resdir$resto$ext#$no\" class=\"quotelink\">&gt;&gt;$no</a>";
	}
	return $m[0];
}
function intraboard_links($proto, $resno) {
	global $intraboard_cb_resno;

	$intraboard_cb_resno = $resno;

	$proto = preg_replace_callback('/&gt;&gt;([0-9]+)/', 'intraboard_link_cb', $proto);
	return $proto;
}

function interboard_link_cb($m) {
	// on one hand, we can link to imgboard.php, using any old subdomain, 
	// and let apache & imgboard.php handle it when they click on the link
	// on the other hand, we can use the database to fetch the proper subdomain
	// and even the resto to construct a proper link to the html file (and whether it exists or not)
	
	// for now, we'll assume there's more interboard links posted than interboard links visited.
	$url = $m[1] . '/' . PHP_SELF . ($m[2] ? ('?res=' . $m[2]) : "");
	return "<a href=\"$url\" class=\"quotelink\">{$m[0]}</a>";	
}
function interboard_rs_link_cb($m) {
	// $m[1] might be a url-encoded query string, or might be manual-typed text
	// so we'll normalize it to raw text first and then re-encode it
	$lsearchquery = urlencode( urldecode($m[1]) );
	return "<a href=\"http://rs.4chan.org/?s=$lsearchquery\" class=\"quotelink\">{$m[0]}</a>";
}

function interboard_dis_link_cb($m) {
	$durl = $m[1]; //i don't think this is useful but just in case
	return "<a href=\"http://dis.4chan.org/read/$durl\" class=\"quotelink\">{$m[0]}</a>";
}

function dis_matching_re() {
	global $dis_matching_re;
	
	if (!$dis_matching_re) {
		$boards = file('./disboards.txt');
		foreach ($boards as $board) {
			list($bn,) = explode("<>", $board);
			$dis_matching_re .= $bn;
			$dis_matching_re .= '|';
		}
		
		$dis_matching_re = substr($dis_matching_re, 0, -1); //lose last |
	}
	
	return $dis_matching_re;
}

function interboard_links($proto) {
	$boards = "an?|cm?|fa|fit|gif|h[cr]?|[bdefgkmnoprstuvxy]|wg?|ic?|y|cgl|c[ko]|mu|po|t[gv]|toy|trv|jp|r9k|sp";
	$disboards = dis_matching_re();
	$proto = preg_replace_callback('@&gt;&gt;&gt;/('.$boards.')/([0-9]*)@i', 'interboard_link_cb', $proto);
	$proto = preg_replace_callback('@&gt;&gt;&gt;/rs/([^\s<>]+)@', 'interboard_rs_link_cb', $proto);
	$proto = preg_replace_callback('@&gt;&gt;&gt;/(('.$disboards.')/[^\s<>]*)@i', 'interboard_dis_link_cb', $proto);
	return $proto;
}

function auto_link($proto,$resno){
	$proto = normalize_links($proto);
		
	// auto-link remaining 4chan.org URLs if they're not part of HTML
	if(strpos($proto,"4chan.org")!==FALSE) {
		$proto = preg_replace('/(http:\/\/(?:[A-Za-z]*\.)?)(4chan)(\.org)(\/)([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?/i',"<a href=\"\\0\" target=\"_blank\">\\0</a>",$proto);
		$proto = preg_replace('/([<][^>]*?)<a href="((http:\/\/(?:[A-Za-z]*\.)?)(4chan)(\.org)(\/)([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?)" target="_blank">\\2<\/a>([^<]*?[>])/i', '\\1\\3\\4\\5\\6\\7\\8', $proto);
	}
	
	$proto = intraboard_links($proto,$resno);
	$proto = interboard_links($proto);
 	return $proto;
}

function auto_ban_poster($nametrip, $banlength, $global, $reason, $pubreason='') {
	if(!$nametrip) $nametrip = S_ANONAME;
	if(strpos($nametrip, '</span> <span class="postertrip">!') !== FALSE) {
		$nameparts=explode('</span> <span class="postertrip">!',$name);
		$nametrip = "{$nameparts[0]} #{$nameparts[1]}";
	}
	$host = $_SERVER['REMOTE_ADDR'];
	$reverse = mysqli_real_escape_string(gethostbyaddr($host));
	$xff = mysqli_real_escape_string(getenv("HTTP_X_FORWARDED_FOR"));
	
	$nametrip = mysqli_real_escape_string($nametrip);
	$global = ($global?1:0);
	$board = BOARD_DIR;
	$reason = mysqli_real_escape_string($reason);
	$pubreason = mysqli_real_escape_string($pubreason);
	if($pubreason) {
		$pubreason .= "<>";
	}

	//if they're already banned on this board, don't insert again
	//since this is just a spam post
	//i don't think it matters if the active ban is global=0 and this one is global=1
	{
		$existingq = mysqli_board_call($con, "select count(*)>0 from ".SQLLOGBAN." where host='$host' and active=1 and (board='$board' or global=1)");
		$existingban = mysqli_result($existingq, 0, 0);
		if ($existingban > 0) {
			delete_uploaded_files();
			die();
		}
	}

	if($banlength == 0) { // warning
		// check for recent warnings to punish spammers
		$autowarnq=mysqli_board_call($con, "SELECT COUNT(*) FROM ".SQLLOGBAN." WHERE host='$host' AND admin='Auto-ban' AND now > DATE_SUB(NOW(),INTERVAL 3 DAY) AND reason like '%$reason'");
		$autowarncount=mysqli_result($autowarnq,0,0);
		if($autowarncount > 3) {
			$banlength = 14;
		}
	}
	
	
	if($banlength == -1) // permanent
		$length = '0000' . '00' .'00'; // YYYY/MM/DD
	else {
		$banlength = (int)$banlength;
		if($banlength < 0) $banlength = 0;
		$length = date("Ymd",time()+$banlength*(24*60*60));
	}
	$length .= "00"."00"."00"; // H:M:S
	
	if(!$result=mysqli_board_call($con, "INSERT INTO ".SQLLOGBAN." (board,global,name,host,reason,length,admin,reverse,xff) VALUES('$board','$global','$nametrip','$host','$pubreason<b>Auto-ban</b>: $reason','$length','Auto-ban','$reverse','$xff')")){echo S_SQLFAIL;}
	@mysqli_free_result($result);
	append_ban($global ? "global" : $global, $host);
}

function check_blacklist($post, $dest) {
    global $con;
	$board = BOARD_DIR;
	$querystr = "SELECT SQL_NO_CACHE * FROM blacklist WHERE active=1 AND (boardrestrict='' or boardrestrict='$board') AND (0 ";
	foreach($post as $field=>$contents) {
		if($contents) {
			$contents = mysqli_real_escape_string(html_entity_decode($contents));
			$querystr .= "OR (field='$field' AND contents='$contents') ";
		}
	}
	$querystr .= ") LIMIT 1";
	$query = mysqli_board_call($querystr);
	if(mysqli_num_rows($query) == 0) return false;
	$row = mysqli_fetch_assoc($query);
	if($row['ban']) {
		$prvreason = "Blacklisted ${row['field']} - " . htmlspecialchars($row['contents']);
		auto_ban_poster($post['trip']?$post['nametrip']:$post['name'], $row['banlength'], 1, $prvreason, $row['banreason']);
	}
	error(S_UPFAIL, $dest);
}


// word-wrap without touching things inside of tags
function wordwrap2($str,$cols,$cut) {
	// if there's no runs of $cols non-space characters, wordwrap is a no-op
        if(strlen($str)<$cols || !preg_match('/[^ <>]{'.$cols.'}/', $str)) {
                return $str;
        }
	$sections = preg_split('/[<>]/', $str);
	$str='';
	for($i=0;$i<count($sections);$i++) {
		if($i%2) { // inside a tag
			$str .= '<' . $sections[$i] . '>';
		}
		else { // outside a tag
			$words = explode(' ',$sections[$i]);
			foreach($words as &$word) {
				$word = wordwrap($word, $cols, $cut, 1);
				// fix utf-8 sequences (XXX: is this slower than mbstring?)
				$lines = explode($cut, $word);
				for($j=1;$j<count($lines);$j++) { // all lines except the first
					while(1) {
						$chr = substr($lines[$j], 0, 1);
						if((ord($chr) & 0xC0) == 0x80) { // if chr is a UTF-8 continuation...
							$lines[$j-1] .= $chr; // put it on the end of the previous line
							$lines[$j] = substr($lines[$j], 1); // take it off the current line
							continue;
						}
							break; // chr was a beginning utf-8 character
					}
				}
				$word = implode($cut, $lines);	
				
			}
			$str .= implode(' ', $words);
		}
	}
	return $str;
}

function cidrtest ($longip, $CIDR) {
	list ($net, $mask) = split ("/", $CIDR);
	
	$ip_net = ip2long ($net);
	$ip_mask = ~((1 << (32 - $mask)) - 1);
	
	$ip_ip = $longip;
	
	$ip_ip_net = $ip_ip & $ip_mask;
	
	return ($ip_ip_net == $ip_net);
}

function  proxy_connect($port) {
  $fp = @fsockopen ($_SERVER["REMOTE_ADDR"], $port,$a,$b,2);
  if(!$fp){return 0;}else{return 1;}
}

function processlist_cleanup($id) {
	//logtime('Done');
	//mysqli_board_call($con, "DELETE FROM proclist WHERE id='$id'");
}

function logtime($desc) {
	static $run = -1;
    global $con;
	if(!defined('PROFILING') && !defined('PROCESSLIST')) return;
	if($run==-1) {
		$run = getmypid(); // rand(0,16777215);
		if(PROCESSLIST == 1) {
			register_shutdown_function('processlist_cleanup', $run);
			$dump = mysqli_real_escape_string(serialize(array('GET'=>$_GET,'POST'=>$_POST,'SERVER'=>$_SERVER)));
			mysqli_board_call($con, "INSERT INTO proclist VALUES ('$run','$dump','')");
		}
	}
	if(PROCESSLIST == 1) {
		mysqli_board_call($con, "UPDATE proclist SET descr='$desc' WHERE id='$run'");
	}
	else {
		$board = BOARD_DIR;
		$time = microtime(true);
		mysqli_board_call($con, "INSERT INTO prof_times VALUES ('$board',$run,$time,'$desc')");
	}
}

function make_american($com) {
	if (stripos($com, "america")!==FALSE) return $com; //already american
	
	$com = rtrim($com);
	$end = '!';
	
	if ($com == "") return $com;

	if (preg_match("/([.!?])$/", $com, $matches)) {$end = $matches[1]; $com = substr($com, 0, -1);}
	
	$com .= " IN AMERICA".$end;
	
	return $com;
}

/* Regist */
function regist($name,$email,$sub,$com,$url,$pwd,$upfile,$upfile_name,$resto,$age){
  global $path,$pwdc,$textonly,$admin,$spoiler,$dest;
  global $con;
  if ($pwd==ADMIN_PASS) $admin=$pwd;
  if ($admin!=ADMIN_PASS || !valid() ) $admin='';
  $mes="";

  if(!$upfile && !$resto) { // allow textonly threads for moderators!
	if(valid('textonly'))
	  	$textonly = 1;
  }
  elseif(JANITOR_BOARD == 1) { // only allow mods/janitors to post, and textonly is always ok
  	$textonly = 1;
	if(!valid('janitor_board'))
		die();
  }


  // time
  $time = time();
  $tim = $time.substr(microtime(),2,3);

 /* logtime("locking tables: ".($resto?'reply':'thread').", ".($upfile?'image':'text'));
  if(PROCESSLIST == 1 && BOARD_DIR != 'b' && 0) {
  	if(!mysqli_board_call($con, "LOCK TABLES ".SQLLOG." WRITE,proclist WRITE"))
  		die(S_SQLCONF.'<!--lk:'.mysqli_errno().'-->');
  }
  else if(BOARD_DIR != 'b' && 0) {
  if(!mysqli_board_call($con, "LOCK TABLES ".SQLLOG." WRITE"))
	die(S_SQLCONF.'<!--lk:'.mysqli_errno().'-->');
}
  logtime("got lock");*/
  $locked_time = time();
  mysqli_board_call($con, "set session query_cache_type=0");
  // check closed
  $resto=(int)$resto;
  if ($resto) {
    if(!$cchk=mysqli_board_call($con, "select closed from ".SQLLOG." where no=".$resto)){echo S_SQLFAIL;}
    list($closed)=mysqli_fetch_row($cchk);
		if ($closed==1&&!$admin) error("You can't reply to this thread anymore.",$upfile);
    mysqli_free_result($cchk);
  }

	if(OEKAKI_BOARD == 1 && $_POST['oe_chk']) {
		require_once 'oekaki.php';
		oe_regist_check();
		$upfile = realpath('tmp/' . $_POST['oe_ip'] . '.png');
		$upfile_name = 'Oekaki';
		$pchfile = realpath('tmp/' . $_POST['oe_ip'] . '.pch');
		if(!file_exists($pchfile)) $pchfile = '';
	}
	
	$has_image = $upfile&&file_exists($upfile);
	
  if($has_image){
   // check image limit
  	if ($resto) {
	    if(!$result=mysqli_board_call($con, "select COUNT(*) from ".SQLLOG." where resto=$resto and fsize!=0")){echo S_SQLFAIL;}
	    $countimgres=mysqli_result($result,0,0);
      if ($countimgres>MAX_IMGRES) error("Max limit of ".MAX_IMGRES." image replies has been reached.",$upfile);
	    mysqli_free_result($result);
		}

  //upload processing
  	$dest = tempnam(substr($path,0,-1), "img");
  	//$dest = $path.$tim.'.tmp';
  	if(OEKAKI_BOARD == 1 && $_POST['oe_chk']) {
  		rename($upfile, $dest);
  		chmod($dest, 0644);
  		if($pchfile)
  			rename($pchfile, "$dest.pch");
  	}
  	else
	  	move_uploaded_file($upfile, $dest);  
	
 	clearstatcache(); // otherwise $dest looks like 0 bytes!
 	//logtime("Moved uploaded file");
 	
    $upfile_name = CleanStr($upfile_name);
	$fsize=filesize($dest);
    if(!is_file($dest)) error(S_UPFAIL,$dest);
    if(!$fsize || $fsize>MAX_KB * 1024) error(S_TOOBIG,$dest);

    // PDF processing
    if(ENABLE_PDF==1 && strcasecmp('.pdf',substr($upfile_name,-4))==0) {
    	$ext='.pdf';
    	$W=$H=1;    	
    	$md5 = md5_of_file($dest);
    	// run through ghostscript to check for validity
    	if(pclose(popen("/usr/bin/gs -q -dSAFER -dNOPAUSE -dBATCH -sDEVICE=nullpage $dest",'w'))) { error(S_UPFAIL,$dest); }
    } else {
    $size = getimagesize($dest);
    if(!is_array($size)) error(S_NOREC,$dest);
    $md5 = md5_of_file($dest);

    //chmod($dest,0666);
    $W = $size[0];
    $H = $size[1];
    switch ($size[2]) {
      case 1 : $ext=".gif";break;
      case 2 : $ext=".jpg";break;
      case 3 : $ext=".png";break;
      case 4 : $ext=".swf";error(S_UPFAIL,$dest);break;
      case 5 : $ext=".psd";error(S_UPFAIL,$dest);break;
      case 6 : $ext=".bmp";error(S_UPFAIL,$dest);break;
      case 7 : $ext=".tiff";error(S_UPFAIL,$dest);break;
      case 8 : $ext=".tiff";error(S_UPFAIL,$dest);break;
      case 9 : $ext=".jpc";error(S_UPFAIL,$dest);break;
      case 10 : $ext=".jp2";error(S_UPFAIL,$dest);break;
      case 11 : $ext=".jpx";error(S_UPFAIL,$dest);break;
      case 13 : $ext=".swf";error(S_UPFAIL,$dest);break;
      default : $ext=".xxx";error(S_UPFAIL,$dest);break;
    }
    if(GIF_ONLY == 1 && $size[2] != 1) error(S_UPFAIL,$dest);
	} // end PDF processing -else
		$insfile=substr($upfile_name, 0, -strlen($ext));
		
	//spam_filter_post_image($name, $dest, $md5, $upfile_name, $ext);

    // Picture reduction
    if (!$resto) {
	    $maxw = MAX_W;
	    $maxh = MAX_H;
    } else {
	    $maxw = MAXR_W;
	    $maxh = MAXR_H;
		}
	if (defined('MIN_W') && MIN_W > $W) error(S_UPFAIL,$dest);
	if (defined('MIN_H') && MIN_H > $H) error(S_UPFAIL,$dest);
	if(defined('MAX_DIMENSION'))
		$maxdimension = MAX_DIMENSION;
	else
		$maxdimension = 5000;
    if ($W > $maxdimension || $H > $maxdimension) {
	    error(S_TOOBIGRES,$dest);
    } elseif($W > $maxw || $H > $maxh){
      $W2 = $maxw / $W;
      $H2 = $maxh / $H;
      ($W2 < $H2) ? $key = $W2 : $key = $H2;
      $TN_W = ceil($W * $key);
      $TN_H = ceil($H * $key);
    }
    $mes = $upfile_name . ' ' . S_UPGOOD;
  }

if(OEKAKI_BOARD == 1 && $_POST['oe_chk']) {
}
else {
  if($_FILES["upfile"]["error"]>0){
  	if($_FILES["upfile"]["error"]==UPLOAD_ERR_INI_SIZE)
	    error(S_TOOBIG,$dest);
  	if($_FILES["upfile"]["error"]==UPLOAD_ERR_FORM_SIZE)
	    error(S_TOOBIG,$dest);
  	if($_FILES["upfile"]["error"]==UPLOAD_ERR_PARTIAL)
	    error(S_UPFAIL,$dest);
  	if($_FILES["upfile"]["error"]==UPLOAD_ERR_CANT_WRITE)
	    error(S_UPFAIL,$dest);
  }
  
  if($upfile_name&&$_FILES["upfile"]["size"]==0){
    error(S_TOOBIGORNONE,$dest);
  }
}

if(ENABLE_EXIF==1) {
	$exif = htmlspecialchars(shell_exec("/usr/bin/exiftags $dest"));
}

  //The last result number
  $lastno = mysqli_result(mysqli_board_call($con, "select max(no) from ".SQLLOG),0,0);

  $resto=(int)$resto;
  if($resto){
	if (!mysqli_result(mysqli_board_call($con, "select count(no) from ".SQLLOG." where root>0 and no=$resto"),0,0))
		error(S_NOTHREADERR,$dest);
  }

  if($_SERVER["REQUEST_METHOD"] != "POST") error(S_UNJUST,$dest);
  // Form content check
  if(!$name||preg_match("/^[ |&#12288;|]*$/",$name)) $name="";
  if(!$com||preg_match("/^[ |&#12288;|\t]*$/",$com)) $com="";
  if(!$sub||preg_match("/^[ |&#12288;|]*$/",$sub))   $sub="";

  if(NO_TEXTONLY==1 && !$admin) {
    if(!$resto&&!$has_image) error(S_NOPIC,$dest);
  } else {
    if(!$resto&&!$textonly&&!$has_image) error(S_NOPIC,$dest);
  }
  if(!trim($com) && !$has_image) error(S_NOTEXT,$dest);

 //$name=preg_replace(S_MANAGEMENT,"\"".S_MANAGEMENT."\"",$name);
 //$name=preg_replace(S_DELETION,"\"".S_DELETION."\"",$name);

if(!$admin && strlen($com) > 2000) error(S_TOOLONG,$dest);
if(strlen($name) > 100) error(S_TOOLONG,$dest);
if(strlen($email) > 100) error(S_TOOLONG,$dest);
if(strlen($sub) > 100) error(S_TOOLONG,$dest);
if(strlen($resto) > 10) error(S_UNUSUAL,$dest);
if(strlen($url) > 10) error(S_UNUSUAL,$dest);

//logtime("starting autoban checks");
	//spam_filter_post_content($com, $sub, $name, $fsize, $resto, $W, $H, $dest, $upfile_name, $email);
	
  //host check
  $host = gethostbyaddr($_SERVER["REMOTE_ADDR"]);
  //$host = $_SERVER["REMOTE_ADDR"];

  //lol /b/
  $xff = getenv("HTTP_X_FORWARDED_FOR");

	//spam_filter_post_ip($dest);
	
	//logtime("inserting xff");
	if (SAVE_XFF==1&&getenv("HTTP_X_FORWARDED_FOR")) {
	mysqli_board_call(sprintf("INSERT INTO xff (tim,board,host) VALUES ('%s','%s','%s')", $tim,BOARD_DIR,mysqli_escape_string(getenv("HTTP_X_FORWARDED_FOR"))) );
	}


  // No, path, time, and url format
  if($pwd==""){
    if($pwdc==""){
      $pwd=rand();$pwd=substr($pwd,0,8);
    }else{
      $pwd=$pwdc;
    }
  }

  $c_pass = $pwd;
  $pass = ($pwd) ? substr(md5($pwd),2,8) : "*";
 $youbi = array(S_SUN, S_MON, S_TUE, S_WED, S_THU, S_FRI, S_SAT);
  $yd = $youbi[date("w", $time)] ;
  if(SHOW_SECONDS == 1) {
 	  $now = date("m/d/y",$time)."(".(string)$yd.")".date("H:i:s",$time);
  } else {
	  $now = date("m/d/y",$time)."(".(string)$yd.")".date("H:i",$time);
  }
  if(DISP_ID){
    if($email&&DISP_ID==1){
      $now .= " ID:???";
    }else{
      $now.=" ID:".substr(crypt(md5($_SERVER["REMOTE_ADDR"].'id'.date("Ymd", $time)),'id'),+3);
    }
  }

  $c_name = $name;
  $c_email = $email;

  if(JANITOR_BOARD == 1) { // now that the cookie_name and _email are separated, we can modify the real ones
  	$name = $_COOKIE['4chan_auser'];
  	$email = '';
  }
  
  //Text plastic surgery (rorororor)
  $email= CleanStr($email);  $email=preg_replace("[\r\n]","",$email);
  $sub  = CleanStr($sub);    $sub  =preg_replace("[\r\n]","",$sub);
  $url  = CleanStr($url);    $url  =preg_replace("[\r\n]","",$url);
  $resto= CleanStr($resto);  $resto=preg_replace("[\r\n]","",$resto);
  $com  = CleanStr($com,1);
  
  if(SPOILERS==1&&$spoiler) { 
  	$sub = "SPOILER<>$sub"; 
  }
  // Standardize new character lines
  $com = str_replace( "\r\n",  "\n", $com);
  $com = str_replace( "\r",  "\n", $com);
  //$com = preg_replace("/\A([0-9A-Za-z]{10})+\Z/", "!s8AAL8z!", $com);
  // Continuous lines
  $com = preg_replace("/\n((&#12288;| )*\n){3,}/","\n",$com);
  
  if(!$admin && substr_count($com,"\n")>MAX_LINES) error("Error: Too many lines.",$dest);
  
  $com = nl2br($com);		//br is substituted before newline char
  
  $com = str_replace("\n",  "", $com);	//\n is erased

if(ROBOT9000==1) {  
   include '/www/global/plugins/robot9000.php';  
   $r9k = robot9000($r9kname,$email,$sub,$com,$md5,ip2long($host),valid('floodbypass'));  
   if($r9k != "ok") error($r9k, $dest);   
}
	if(ENABLE_EXIF==1 && $exif) {
		//turn exif into a table
		$exiflines = explode("\n",$exif);
		$exif = "<table class=\"exif\" id=\"exif$tim\" style=\"display:none;\">";
		foreach($exiflines as $exifline) {
			list($exiftag,$exifvalue) = explode(': ',$exifline);
			if($exifvalue != '')
				$exif .= "<tr><td>$exiftag</td><td>$exifvalue</td></tr>";
			else
				$exif .= "<tr><td><b>$exiftag</b></td></tr>";
		}
		$exif .= '</table>';
		$com .= "<br/><span class=\"abbr\">EXIF data available. Click <a href=\"javascript:void(0)\" onclick=\"toggle('exif$tim')\">here</a> to show/hide.</span><br/>";
		$com .= "$exif";
	}
	if(OEKAKI_BOARD==1 && $_POST['oe_chk']) {
		$com .= oe_info($dest,$tim);
	}

  //$name=preg_replace("&#9670;","&#9671;",$name);  //replace filled diamond with hollow diamond (sjis)
  $name=preg_replace("[\r\n]","",$name);
  $names=iconv("UTF-8", "CP932//IGNORE", $name); // convert to Windows Japanese #&#65355;&#65345;&#65357;&#65353;

  //start new tripcode crap
	list ($name) = explode("#", $name);
    $name = CleanStr($name);

	if(preg_match("/\#+$/", $names)){
	    $names = preg_replace("/\#+$/", "", $names);
	}
	if (preg_match("/\#/", $names)) {
	    $names = str_replace("&#","&&",htmlspecialchars($names)); # otherwise HTML numeric entities screw up explode()!
	    list ($nametemp,$trip,$sectrip) = str_replace("&&", "&#", explode("#",$names,3));
	    $names = $nametemp;
	    $name .= "</span>";

	    if ($trip != "") {
	    	if (FORTUNE_TRIP == 1 && $trip == "fortune") {
	    		$fortunes = array("Bad Luck","Average Luck","Good Luck","Excellent Luck","Reply hazy, try again","Godly Luck","Very Bad Luck","Outlook good","Better not tell you now","You will meet a dark handsome stranger","&#65399;&#65408;&#9473;&#9473;&#9473;&#9473;&#9473;&#9473;(&#65439;&#8704;&#65439;)&#9473;&#9473;&#9473;&#9473;&#9473;&#9473; !!!!","&#65288;&#12288;Ã‚Â´_&#12445;`&#65289;&#65420;&#65392;&#65437; ","Good news will come to you by mail");
	    		$fortunenum = rand(0,sizeof($fortunes)-1);
	    		$fortcol = "#" . sprintf("%02x%02x%02x", 
	    			127+127*sin(2*M_PI * $fortunenum / sizeof($fortunes)),
	    			127+127*sin(2*M_PI * $fortunenum / sizeof($fortunes)+ 2/3 * M_PI),
	    			127+127*sin(2*M_PI * $fortunenum / sizeof($fortunes) + 4/3 * M_PI));
	    		$com = "<font color=$fortcol><b>Your fortune: ".$fortunes[$fortunenum]."</b></font><br /><br />".$com;
	    		$trip = "";
	    		if($sectrip == "") {
		    		if($name == "</span>" && $sectrip == "") 
		    			$name = S_ANONAME;
		 		else 
		 			$name = str_replace("</span>","",$name);	
		}
	    } else if($trip=="fortune") {
		//remove fortune even if FORTUNE_TRIP is off
		$trip="";
                if($sectrip == "") {
                        if($name == "</span>" && $sectrip == "")
                                $name = S_ANONAME;
                        else
                                $name = str_replace("</span>","",$name);
                }

	    } else {

	        $salt = strtr(preg_replace("/[^\.-z]/",".",substr($trip."H.",1,2)),":;<=>?@[\\]^_`","ABCDEFGabcdef");
	        $trip = substr(crypt($trip, $salt),-10);
	        $name.=" <span class=\"postertrip\">!".$trip;
	        }
	    }


	    if ($sectrip != "") {
	        $salt = "LOLLOLOLOLOLOLOLOLOLOLOLOLOLOLOL"; #this is ONLY used if the host doesn't have openssl
	                                                #I don't know a better way to get random data
	    if (file_exists(SALTFILE)) { #already generated a key
	        $salt = file_get_contents(SALTFILE);
	    } else {
	        system("openssl rand 448 > '".SALTFILE."'",$err);
	        if ($err === 0) {
	            chmod(SALTFILE,0400);
	            $salt = file_get_contents(SALTFILE);
	        }
	    }
	        $sha = base64_encode(pack("H*",sha1($sectrip.$salt)));
	        $sha = substr($sha,0,11);
			    if($trip=="") $name.=" <span class=\"postertrip\">";
			    $name.="!!".$sha;
	    }
	} //end new tripcode crap

	if(!$name) $name=S_ANONAME;
	if(!$com) $com=S_ANOTEXT;
	if(!$sub) $sub=S_ANOTITLE;

	if(DICE_ROLL==1) {
	if ($email) {
		if (preg_match("/dice[ +](\\d+)[ d+](\\d+)(([ +-]+?)(-?\\d+))?/", $email, $match)) {
			$dicetxt = "rolled ";
			$dicenum = min(25, $match[1]);
			$diceside = $match[2];
			$diceaddexpr = $match[3];
			$dicesign = $match[4];
			$diceadd = intval($match[5]);
			
			for ($i = 0; $i < $dicenum; $i++) {
				$dicerand = mt_rand(1, $diceside);
				if ($i) $dicetxt .= ", ";
				$dicetxt .= $dicerand;
				$dicesum += $dicerand;
			}
			
			if ($diceaddexpr) {
				if (strpos($dicesign, "-") > 0) $diceadd *= -1;
				$dicetxt .= ($diceadd >= 0 ? " + " : " - ").abs($diceadd);
				$dicesum += $diceadd;
			}
			
			$dicetxt .= " = $dicesum<br /><br />";
			$com = "<b>$dicetxt</b>".$com;
		}
	}
	}
	$emails=$email;
  if(preg_match("/(#|&#65283;)(.*)/",$emails,$regs)){
    if ($regs[2]=="pubies") {
    	list($email)=explode("#",$email,2);
    	//if(valid()) {
	      $color1="#800080";
	      $color2="#900090";
	      $ma="Mod";
	      if(stristr($name,"moot")||stristr($name,"coda")) {
	        $color1="#F00000";
	        $color2="#FF0000";
	        $ma="Admin";
	      }
	      $name="<span title='$email' style=\"color:$color1\">".$name;
	      $name=str_replace(" <span class=\"postertrip\">","</span> <span class=\"postertrip\"><span title='$email' style=\"color:$color2;font-weight:normal\">",$name);
	      $name.="</span></span> <span class=\"commentpostername\"><span title='$email' style=\"color:$color1\">## $ma</span>";
      //}
      	$email = '';
 /*   } elseif ($regs[2]=="munroexkcd") {
	    $name="<span style=\"color:#0000F0\">".$name;
	    $name=str_replace(" <span class=\"postertrip\">","</span> <span class=\"postertrip\"><span style=\"color:#0000FF;font-weight:normal\">",$name);
	    $name.="</span></span> <span class=\"commentpostername\"><span style=\"color:#0000F0\">## BlOgGeR</span>";
      list($email)=explode("#",$email,2);
    } elseif ($regs[2]=="netkingdongs") {
            $name='</span> <span class="postertrip">!!NETKING...';
            list($email)=explode("#",$email,2);
    } elseif ($regs[2]=="redhammer") {
    	if(!valid()) auto_ban("<b>autobanmenow</b>",$name,"redhammer capcode");
      list($email)=explode("#",$email,2); */
    }
  }

	$nameparts=explode('</span> <span class="postertrip">!',$name);
	/*
    check_blacklist(array(
		'name' => $nameparts[0], 
		'trip' => $trip, 
		'nametrip' => "{$nameparts[0]} #{$trip}", 
		'md5' => $md5, 
		'email' => $email, 
		'sub' => $sub, 
		'com' => $com, 
		'pwd' => $pass, 
		'xff' => $xff,
		'filename' => $insfile,
		), $dest);
    */
	//spam_filter_post_trip($name, $trip, $dest);
	
	if(SPOILERS==1) {
		$com = spoiler_parse($com);
	}
	if(SAGE_FILTER==1&&(stripos($sub,"sage")!==FALSE||stripos($com,"sage")!==FALSE)&&stripos($email,"sage")!==FALSE) $email=""; //lol /b/
	if(WORD_FILT&&file_exists("wf.php")){
		$com = word_filter($com,"com");
		if($sub)
			$sub = word_filter($sub,"sub");
		$com = str_replace(":getprophet:",$no,$com);
		$namearr=explode('</span> <span class="postertrip">',$name);
		if (strstr($name,'</span> <span class="postertrip">')) { $nametrip='</span> <span class="postertrip">'.$namearr[1]; } else { $nametrip=""; }
		if($namearr[0] != S_ANONAME)
			$name = word_filter($namearr[0],"name").$nametrip;
	}

	if(FORCED_ANON==1) {$name = "</span>$now<span>"; $sub = ''; $now = '';}
	$com = wordwrap2($com, 100, "<br />");
	$com = preg_replace("!(^|>)(&gt;[^<]*)!", "\\1<font class=\"unkfunc\">\\2</font>", $com);
	
	$is_sage = stripos($email, "sage") !== FALSE;
	//post is now completely created(?)

	//logtime("Before flood check");
	$may_flood = valid('floodbypass');
	
	if (!$may_flood) {
		if ($com) {
			// Check for duplicate comments
			$query="select count(no)>0 from ".SQLLOG." where com='".mysqli_escape_string($con, $com)."' ".
				"and host='".mysqli_escape_string($con, $host)."' ".
				"and time>".($time-RENZOKU_DUPE);
			$result=mysqli_board_call($con, $query);
			if(mysqli_result($result,0,0))error(S_RENZOKU,$dest);
			mysqli_free_result($result);
		}

		if (!$has_image) {
			// Check for flood limit on replies
			$query="select count(no)>0 from ".SQLLOG." where time>".($time - RENZOKU)." ".
				"and host='".mysqli_escape_string($con, $host)."' and resto>0";
			$result=mysqli_board_call($con, $query);
			if(mysqli_result($result,0,0))error(S_RENZOKU, $dest);
			mysqli_free_result($result);
		}
		
		if ($is_sage) {
			// Check flood limit on sage posts
			$query="select count(no)>0 from ".SQLLOG." where time>".($time - RENZOKU_SAGE)." ".
				"and host='".mysqli_escape_string($host)."' and resto>0 and permasage=1";
			$result=mysqli_board_call($con, $query);
			if(mysqli_result($result,0,0))error(S_RENZOKU, $dest);
			mysqli_free_result($result);
		}
		
		if (!$resto) {
			// Check flood limit on new threads
			$query="select count(no)>0 from ".SQLLOG." where time>".($time - RENZOKU3)." ".
				"and host='".mysqli_escape_string($con, $host)."' and root>0"; //root>0 == non-sticky
			$result=mysqli_board_call($con, $query);
			if(mysqli_result($result,0,0))error(S_RENZOKU3, $dest);
			mysqli_free_result($result);
		}
	}

	// Upload processing
	if($has_image) {
		if(!$may_flood) {
			$query="select count(no)>0 from ".SQLLOG." where time>".($time - RENZOKU2)." ".
				"and host='".mysqli_escape_string($con, $host)."' and resto>0";
			$result=mysqli_board_call($con, $query);
			if(mysqli_result($result,0,0))error(S_RENZOKU2,$dest);
			mysqli_free_result($result);
		}

		//Duplicate image check
		$result = mysqli_board_call($con, "select no,resto from ".SQLLOG." where md5='$md5'");
		if(mysqli_num_rows($result)){
			list($dupeno,$duperesto) = mysqli_fetch_row($result);
			if(!$duperesto) $duperesto = $dupeno;
			error('<a href="'.BOARD_DIR . "/res/" . $duperesto . PHP_EXT . '#' . $dupeno . '">'.S_DUPE.'</a>',$dest);
		}
		mysqli_free_result($result);
	}

   $rootqu = $resto ? "0" : "now()";

	// thumbnail
  if($has_image){
    rename($dest,$path.$tim.$ext);
    if(USE_THUMB){
		$tn_name = thumb($path,$tim,$ext,$resto);
		if (!$tn_name && $ext != ".pdf") {
			error(S_UNUSUAL);
		}
		}
    if(OEKAKI_BOARD == 1 && $_POST['oe_chk']) {
    	rename("$dest.pch",$path.$tim.'.pch');
    	unlink($upfile); // get rid of the tmp/ entries
    	unlink($pchfile);
    }
  }
//logtime("Thumbnail created");

	//logtime("Before insertion");
	// noko (stay) actions
	if($email == 'noko') {
		$email = ''; $noko = 1;
	}
	else if($email == 'noko2') {
		$email = ''; $noko = 2;
	}
	
	//find sticky & autosage
	// auto-sticky
	$sticky = false;
	//$autosage = spam_filter_should_autosage($com, $sub, $name, $fsize, $resto, $W, $H, $dest, $insertid);
	
	if(defined('AUTOSTICKY') && AUTOSTICKY) {
		$autosticky = preg_split("/,\s*/", AUTOSTICKY);
		if($resto == 0) {
			if($insertid % 1000000 == 0 || in_array($insertid,$autosticky))
				$sticky = true;
		}
	}
	
	$flag_cols = "";
	$flag_vals = "";
	
	if ($sticky) {
		$flag_cols = ",sticky";
		$flag_vals = ",1";
	}
	
	//permasage just means "is sage" for replies
	if ($resto ? $is_sage : $autosage) {
		$flag_cols .= ",permasage";
		$flag_vals .= ",1";
	}
	
  $query="insert into ".SQLLOG." (now,name,email,sub,com,host,pwd,filename,ext,w,h,tn_w,tn_h,tim,time,md5,fsize,root,resto$flag_cols) values (".
"'".$now."',".
"'".mysqli_escape_string($con, $name)."',".
"'".mysqli_escape_string($con, $email)."',".
"'".mysqli_escape_string($con, $sub)."',".
"'".mysqli_escape_string($con, $com)."',".
"'".mysqli_escape_string($con, $host)."',".
"'".mysqli_escape_string($con, $pass)."',".
"'".mysqli_escape_string($con, $insfile)."',".
"'".$ext."',".
(int)$W.",".
(int)$H.",".
(int)$TN_W.",".
(int)$TN_H.",".
"'".$tim."',".
(int)$time.",".
"'".$md5."',".
(int)$fsize.",".
$rootqu.",".
(int)$resto.
$flag_vals.")";
  if(!$result=mysqli_board_call($con, $query)){echo S_SQLFAIL;}  //post registration

	$cookie_domain = '.example.com';
  //Cookies
  setrawcookie("4chan_name", rawurlencode($c_name), time()+($c_name?(7*24*3600):-3600),'/',$cookie_domain);
  //header("Set-Cookie: 4chan_name=$c_name; expires=".date("D, d-M-Y H:i:s",time()+7*24*3600)." GMT",false);
  if (($c_email!="sage")&&($c_email!="age")){
  	setcookie ("4chan_email", $c_email,time()+($c_email?(7*24*3600):-3600),'/',$cookie_domain);  // 1 week cookie expiration
  }
  setcookie("4chan_pass", $c_pass,time()+7*24*3600,'/',$cookie_domain);  // 1 week cookie expiration

  $insertid = mysqli_insert_id($con);
	
	if($resto){ //sage or age action
		$resline=mysqli_board_call($con, "select count(no) from ".SQLLOG." where resto=".$resto);
		$countres=mysqli_result($resline,0,0); 
		mysqli_free_result($resline);
		$resline=mysqli_board_call($con, "select sticky,permasage from ".SQLLOG." where no=".$resto);
		list($stuck,$psage)=mysqli_fetch_row($resline);
		mysqli_free_result($resline);
		if((stripos($email,'sage')===FALSE && $countres < MAX_RES && $stuck != "1" && $psage != "1") || ($admin&&$age&&$stuck != "1")){
			$query="update ".SQLLOG." set root=now() where no=$resto"; //age
			mysqli_board_call($con, $query);
		}
	}
  	
	$static_rebuild = defined("STATIC_REBUILD") && (STATIC_REBUILD==1);
	//logtime("Before trim_db");  
	// trim database
	if(!$resto && !$static_rebuild)
	  trim_db();
	//logtime("After trim_db");
if(PROCESSLIST == 1 && (time() > ($locked_time+7))) {
			$dump = mysqli_real_escape_string(serialize(array('GET'=>$_GET,'POST'=>$_POST,'SERVER'=>$_SERVER)));
			mysqli_board_call($con, "INSERT INTO proclist VALUES (connection_id(),'$dump','slow post')");
}
/*mysqli_board_unlock();
 
 	//logtime("Tables unlocked");	*/
if(BOARD_DIR == 'b')
iplog_add(BOARD_DIR, $insertid, $host);
	//logtime("Ip logged");

if(RAPIDSEARCH_LOGGING == 1) {
	rapidsearch_check(BOARD_DIR, $insertid, $com);
}
	//logtime("rapidsearch check finished");

	$deferred = false;
	// update html
	if($resto) {
  	$deferred = updatelog($resto, $static_rebuild);
	} else {
	  $deferred = updatelog($insertid, $static_rebuild);
  }
  //logtime("Pages rebuilt");
  // determine url to redirect to 
	if($noko && !$resto) {
		$redirect = BOARD_DIR . "/res/" . $insertid . PHP_EXT;
	}
	else if($noko==1) {
		$redirect = BOARD_DIR . "/res/" . $resto . PHP_EXT . '#' . $insertid;	
	}
	else {
		$redirect = PHP_SELF2_ABS;
	}

  if($deferred) {
	echo "<html><head><META HTTP-EQUIV=\"refresh\" content=\"2;URL=$redirect\"></head>";
	echo "<body>$mes ".S_SCRCHANGE."<br>Your post may not appear immediately.<!-- thread:$resto,no:$insertid --></body></html>";
  }
  else {
	echo "<html><head><META HTTP-EQUIV=\"refresh\" content=\"1;URL=$redirect\"></head>";
	echo "<body>$mes ".S_SCRCHANGE."<!-- thread:$resto,no:$insertid --></body></html>";
  }

}

function resredir($res) {
    global $con;
	$res = (int)$res;
	//mysqli_board_lock();
	if(!$redir=mysqli_board_call($con, "select no,resto from ".SQLLOG." where no=".$res)){echo S_SQLFAIL;}
	list($no,$resto)=mysqli_fetch_row($redir);
	if(!$no) {
		$maxq = mysqli_board_call($con, "select max(no) from ".SQLLOG."");
		list($max)=mysqli_fetch_row($maxq);
		if(!$max || ($res > $max))
			header("HTTP/1.0 404 Not Found");
		else // res < max, so it must be deleted!
			header("HTTP/1.0 410 Gone");
		error(S_NOTHREADERR,$dest);
	}
  
  if($resto=="0") // thread
  	$redirect = BOARD_DIR . "/res/" . $no . PHP_EXT . '#' . $no;
  else
  	$redirect = BOARD_DIR . "/res/" . $resto . PHP_EXT . '#' . $no;  
  
	
	echo "<META HTTP-EQUIV=\"refresh\" content=\"0;URL=$redirect\">";
	if($resto=="0")
		log_cache();
	//mysqli_board_unlock();
	
	if($resto=="0") { // thread
		updatelog($res);
	}
}

//thumbnails
function thumb($path,$tim,$ext,$resto){
  if(!function_exists("ImageCreate")||!function_exists("ImageCreateFromJPEG"))return;
  $fname=$path.$tim.$ext;
  $thumb_dir = THUMB_DIR;     //thumbnail directory
  $outpath = $thumb_dir.$tim.'s.jpg';
  if (!$resto) {
	  $width     = MAX_W;            //output width
	  $height    = MAX_H;            //output height
  } else {
	  $width     = MAXR_W;            //output width (imgreply)
	  $height    = MAXR_H;            //output height (imgreply)
  }
  // width, height, and type are aquired
  if(ENABLE_PDF==1 && $ext=='.pdf') {
  	// create jpeg for the thumbnailer
  	$pdfjpeg = $path.$tim.'.pdf.tmp';
  	@exec("/usr/bin/gs -q -dSAFER -dNOPAUSE -dBATCH -sDEVICE=jpeg -sOutputFile=$pdfjpeg $fname");
  	if(!file_exists($pdfjpeg)) unlink($fname);
  	$fname = $pdfjpeg;
  }
  $size = GetImageSize($fname);
  $memory_limit_increased = false;
  if($size[0]*$size[1] > 3000000) {
  	$memory_limit_increased = true;
	  ini_set('memory_limit', memory_get_usage() + $size[0]*$size[1]*10); // for huge images
  }
  switch ($size[2]) {
    case 1 :
      if(function_exists("ImageCreateFromGIF")){
        $im_in = ImageCreateFromGIF($fname);
        if($im_in){break;}
      }
      if(!is_executable(realpath("./gif2png"))||!function_exists("ImageCreateFromPNG"))return;
      @exec(realpath("./gif2png")." $fname",$a);
      if(!file_exists($path.$tim.'.png'))return;
      $im_in = ImageCreateFromPNG($path.$tim.'.png');
      unlink($path.$tim.'.png');
      if(!$im_in)return;
      break;
    case 2 : $im_in = ImageCreateFromJPEG($fname);
      if(!$im_in){return;}
       break;
    case 3 :
      if(!function_exists("ImageCreateFromPNG"))return;
      $im_in = ImageCreateFromPNG($fname);
      if(!$im_in){return;}
      break;
    default : return;
  }
  // Resizing
  if ($size[0] > $width || $size[1] > $height || $size[2]==1) {
    $key_w = $width / $size[0];
    $key_h = $height / $size[1];
    ($key_w < $key_h) ? $keys = $key_w : $keys = $key_h;
    $out_w = ceil($size[0] * $keys) +1;
    $out_h = ceil($size[1] * $keys) +1;
    /*if ($size[2]==1) {
	    $out_w = $size[0];
	    $out_h = $size[1];
    } //what was this for again? */
  } else {
    $out_w = $size[0];
    $out_h = $size[1];
  }
  // the thumbnail is created
  if(function_exists("ImageCreateTrueColor")&&get_gd_ver()=="2"){
    $im_out = ImageCreateTrueColor($out_w, $out_h);
  }else{$im_out = ImageCreate($out_w, $out_h);}
  // copy resized original
  ImageCopyResampled($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
  // thumbnail saved
  ImageJPEG($im_out, $outpath ,75);
  //chmod($thumb_dir.$tim.'s.jpg',0666);
  // created image is destroyed
  ImageDestroy($im_in);
  ImageDestroy($im_out);
  if(isset($pdfjpeg)) { unlink($pdfjpeg); } // if PDF was thumbnailed delete the orig jpeg
  if($memory_limit_increased)
  	ini_restore('memory_limit');

  return $outpath;
}

//check version of gd
function get_gd_ver(){
  if(function_exists("gd_info")){
    $gdver=gd_info();
    $phpinfo=$gdver["GD Version"];
  }else{ //earlier than php4.3.0
    ob_start();
    phpinfo(8);
    $phpinfo=ob_get_contents();
    ob_end_clean();
    $phpinfo=strip_tags($phpinfo);
    $phpinfo=stristr($phpinfo,"gd version");
    $phpinfo=stristr($phpinfo,"version");
  }
  $end=strpos($phpinfo,".");
  $phpinfo=substr($phpinfo,0,$end);
  $length = strlen($phpinfo)-1;
  $phpinfo=substr($phpinfo,$length);
  return $phpinfo;
}

//md5 calculation for earlier than php4.2.0
function md5_of_file($inFile) {
 if (file_exists($inFile)){
  if(function_exists('md5_file')){
    return md5_file($inFile);
  }else{
    $fd = fopen($inFile, 'r');
    $fileContents = fread($fd, filesize($inFile));
    fclose ($fd);
    return md5($fileContents);
  }
 }else{
  return false;
}}

/* text plastic surgery */
// you can call with skip_bidi=1 if cleaning a paragraph element (like $com)
function CleanStr($str,$skip_bidi=0){
  global $admin,$html;
  $str = trim($str);//blankspace removal
  if (get_magic_quotes_gpc()) {//magic quotes is deleted (?)
    $str = stripslashes($str);
  }
  if($admin!=ADMIN_PASS){
    $str = htmlspecialchars($str);
  }	elseif(( $admin==ADMIN_PASS)&&$html!=1) {
    $str = htmlspecialchars($str);
  }
  if($skip_bidi == 0) {
	  // fix malformed bidirectional overrides - insert as many PDFs as RLOs
	//RLO
	  $str .= str_repeat("\xE2\x80\xAC", substr_count($str, "\xE2\x80\xAE"/* U+202E */));
   	  $str .= str_repeat("&#8236;", substr_count($str, "&#8238;"));
	  $str .= str_repeat("&#x202c;", substr_count($str, "&#x202e;"));
	//RLE
      $str .= str_repeat("\xE2\x80\xAC", substr_count($str, "\xE2\x80\xAB"/* U+202B */));
   	  $str .= str_repeat("&#8236;", substr_count($str, "&#8235;"));
	  $str .= str_repeat("&#x202c;", substr_count($str, "&#x202b;"));
  }
  return str_replace(",", "&#44;", $str);//remove commas
}

function table_exist($link, $table){
  $result = mysqli_board_call($link, "show tables like '$table'");
  if(!$result){return 0;}
  $a = mysqli_fetch_row($result);
  mysqli_free_result($result);
  return $a;
}

function report() {
	require '/www/global/forms/report.php';
	require '/www/global/modes/report.php';
	if($_SERVER['REQUEST_METHOD'] == 'GET') {
		if(!report_post_exists($_GET['no']))
			fancydie('That post doesn\'t exist anymore.');
		if(report_post_sticky($_GET['no']))
			fancydie('Stop trying to report a sticky.');
		report_check_ip(BOARD_DIR, $_GET['no']);
		form_report(BOARD_DIR, $_GET['no']);
	}
	else {
		report_check_ip(BOARD_DIR, $_POST['no']);
		report_submit(BOARD_DIR, $_POST['no'], $_POST['cat']);
	}
	die('</body></html>');
}

/* user image deletion */
function usrdel($no,$pwd){
  global $path,$pwdc,$onlyimgdel;
  global $con;
  $host = $_SERVER["REMOTE_ADDR"];
  $delno = array();
  $delflag = FALSE;
  $rebuildindex = !(defined("STATIC_REBUILD") && STATIC_REBUILD);
  reset($_POST);
  while ($item = each($_POST)){
    if($item[1]=='delete'){array_push($delno,$item[0]);$delflag=TRUE;}
  }
  if(($pwd=="")&&($pwdc!="")) $pwd=$pwdc;
  $countdel=count($delno);

  $flag = FALSE;
  //mysqli_board_call($con, "LOCK TABLES ".SQLLOG." WRITE");
  $rebuild = array(); // keys are pages that need to be rebuilt (0 is index, of course)
  for($i = 0; $i<$countdel; $i++){
  	$resto = delete_post($delno[$i], $pwd, $onlyimgdel, 0, 1, $countdel == 1); // only show error for user deletion, not multi
  	if($resto)
	  	$rebuild[$resto] = 1;
  }
  log_cache();
  //mysqli_board_call($con, "UNLOCK TABLES");  
  foreach($rebuild as $key=>$val) {
  	updatelog($key, 1); // leaving the second parameter as 0 rebuilds the index each time!
  }
  if ($rebuildindex) updatelog(); // update the index page last
}

/*password validation */
function oldvalid($pass){
	//error(S_WRONGPASS);
  if($pass && ($pass != ADMIN_PASS) ) {
	//auto_ban_poster($name, 2, 1, 'failed the password check on imgboard manager mode', 'Trying to exploit administrative pages.');
	error(S_WRONGPASS);
  }

  head($dat,0);
  echo $dat;
  echo "[<a href=\"".PHP_SELF2."\">".S_RETURNS."</a>]\n";
  echo "[<a href=\"".PHP_SELF."\">".S_LOGUPD."</a>]\n";
  echo "<table width='100%'><tr><th bgcolor=#E08000>\n";
  echo "<font color=#FFFFFF>".S_MANAMODE."</font>\n";
  echo "</th></tr></table>\n";
  echo "<p><form action=\"".PHP_SELF."\" method=POST>\n";
  // Mana login form
  if(!$pass){
    echo "<center><input type=hidden name=admin value=post><input type=hidden name=mode value=admin>\n";
    echo "<input class=inputtext type=password name=pass size=8>";
    echo "<input type=submit value=\"".S_MANASUB."\"></form></center>\n";
    die("</body></html>");
  }
}

function rebuild($all=0) {
    global $con;
	header("Pragma: no-cache");
	echo "Rebuilding ";
	if($all) { echo "all"; } else { echo "missing"; }
	echo " replies and pages... <a href=\"".PHP_SELF2_ABS."\">Go back</a><br><br>\n";
	ob_end_flush();
	//mysqli_board_lock();
	$starttime = microtime(true);
	if(!$treeline=mysqli_board_call($con, "select no,resto from ".SQLLOG." where root>0 order by root desc")){echo S_SQLFAIL;}
	log_cache();
	//mysqli_board_unlock();
	echo "Writing...\n";
	if($all || !defined('CACHE_TTL')) {
		while(list($no,$resto)=mysqli_fetch_row($treeline)) {
			if(!$resto) {
				updatelog($no,1);
				echo "No.$no created.<br>\n";
    			}
		}
		updatelog();
		echo "Index pages created.<br>\n";
	}
	else {
		$posts = rebuildqueue_take_all();
		foreach($posts as $no) {
			$deferred = ( updatelog($no,1) ? ' (deferred)' : '' );
			if($no)
				echo "No.$no created.$deferred<br>\n";
			else
				echo "Index pages created.$deferred<br>\n";
		}
	}
	$totaltime = microtime(true) - $starttime;
	echo "<br>Time elapsed (lock excluded): $totaltime seconds","<br>Pages created.<br><br>\nRedirecting back to board.\n<META HTTP-EQUIV=\"refresh\" content=\"10;URL=".PHP_SELF2."\">";
}

/*-----------Main-------------*/
switch($mode){
  case 'regist':
    regist($name,$email,$sub,$com,'',$pwd,$upfile,$upfile_name,$resto,$age);
    break;
  case 'report':
    report();
    break;
  case 'admin':
    oldvalid($pass);
    if($admin=="post"){
      echo "</form>";
      form($post,$res,1);
      echo $post;
      die("</body></html>");
    }
    break;
  case 'rebuild':
      rebuild();
      break;
  case 'rebuildall':
      rebuild(1);
      break;
  case 'admindel':
      usrdel($no,$pwd);
      echo "<META HTTP-EQUIV=\"refresh\" content=\"0;URL=admin.php\">";
      break;
  case 'nothing':
	  break;
  case 'usrdel':
      usrdel($no,$pwd);
  default:
  if(JANITOR_BOARD == 1 && $mode == 'latest') {
  	broomcloset_latest();
  }
  if(OEKAKI_BOARD == 1 && $mode == 'oe_finish') {
   require_once 'oekaki.php';
   oe_finish();
  }
  elseif(OEKAKI_BOARD == 1 && $mode == 'oe_paint') {
   require_once 'oekaki.php';
   oe_paint();
  }
  if($res){
      resredir($res);
      echo "<META HTTP-EQUIV=\"refresh\" content=\"10;URL=".PHP_SELF2_ABS."\">";
    }else{
	//mysqli_board_call($con, "LOCK TABLES ".SQLLOG." READ");
	  echo "Updating index...\n";
      	updatelog();
     //mysqli_board_call($con, "UNLOCK TABLES");
      echo "<META HTTP-EQUIV=\"refresh\" content=\"0;URL=".PHP_SELF2_ABS."\">";
    }
}

?>
