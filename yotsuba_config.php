<?
define('TITLE', 'Yotsuba Image Board');		//Name of this image board
define('SQLLOG', 'CHANGEME');		//Table (NOT DATABASE) used by image board
define('SQLHOST', 'CHANGEME');		//MySQL server address', usually localhost
define('SQLUSER', 'CHANGEME');		//MySQL user (must be changed)
define('SQLPASS', 'CHANGEME');		//MySQL user's password (must be changed)
define('SQLDB', 'CHANGEME');		//Database used by image board
define('ADMIN_PASS', 'CHANGEME');	//Janitor password  (CHANGE THIS YO)
define('ADMIN_PASS2', 'CHANGEME');  //uuh
define('SHOWTITLETXT', 1);		//Show TITLE at top (1: yes  0: no)
define('SHOWTITLEIMG', 0);		//Show image at top (0: no', 1: single', 2: rotating)
define('TITLEIMG', 'title.jpg');	//Title image (point to php file if rotating)
define('IMG_DIR', 'src/');		//Image directory (needs to be 777)
define('IMG_DIR2', 'https://example.com/b/src/');			//Absolute path (Image directory)
define('THUMB_DIR', 'thumb/');		//Thumbnail directory (needs to be 777)
define('THUMB_DIR2', 'https://example.com/b/thumb/');			//Absolute path (Thumbnail directory)
define('RES_DIR', 'res/');
define('HOME',  '../');			//Site home directory (up one level by default
define('MAX_KB', 2048);			//Maximum upload size in KB
define('MAX_W',  250);			//Images exceeding this width will be thumbnailed
define('MAX_H',  250);			//Images exceeding this height will be thumbnailed
define('MAXR_W', 125);          //Image replys exceeding this width will be thumbnailed
define('MAXR_H', 125);          //Image replys exceeding this height will be thumbnailed
define('PAGE_DEF', 5);			//Images per page
define('LOG_MAX',  500);		//Maxium number of entries
define('PHP_SELF', 'imgboard.php');	//Name of main script file
define('PHP_SELF2', 'imgboard.html');	//Name of main html file
define('PHP_SELF_ABS', 'https://example.com/b/imgboard.php');	//Absolute path of main script file
define('PHP_SELF2_ABS', 'https://example.com/b/imgboard.html');	//Absolute path of main html file
define('PHP_EXT', '.html');		//Extension used for board pages after first
define('FILE_APPEND', '.html');
define('RENZOKU', 1);			//Seconds between posts (floodcheck)
define('RENZOKU2', 1);		//Seconds between image posts (floodcheck)
define('RENZOKU3', 1);
define('RENZOKU_DUPE', 1);
define('MAX_RES', 30);		//Maximum topic bumps
define('USE_THUMB', 1);		//Use thumbnails (1: yes  0: no)
define('PROXY_CHECK', 0);		//Enable proxy check (1: yes  0: no)
define('DISP_ID', 0);		//Display user IDs (1: yes  0: no)
define('BR_CHECK', 0);		//Max lines per post (0 = no limit)
define('SUBTITLE', 'test');		//Subtitle for this image board
define('BOARD_DIR', 'test');    //Board URI (i.e /b/)
define('DATA_SERVER', 'https://s.4cdn.org/image/');    //Your CDN for static files
define('EXPIRE_NEGLECTED', 1);  //Truncate neglected threads? (not %100 sure if this works)
define('PAGE_MAX', 16);
define('DEFAULT_BURICHAN', 0);  //Is this a blue board? (1: yes  0: no)
define('RTA', 0);       //RTA META Tag for red boards (1: yes  0: no)
define('META_ROBOTS', 'noarchive'); //META tag
define('META_DESCRIPTION', 'Yotsuba Image Board'); //META tag
define('META_KEYWORDS', 'imageboard,anonymous,random,/b/'); //META tag
define('FORCED_ANON', 0);   //Remove the name field (1: yes  0: no)
define('SPOILERS', 0);      //Enable spoilers? (1: yes  0: no)
define('SPOILER_THUMB', 'https://s.4cdn.org/image/spoiler.png');
define('MAX_IMGRES', 300);
define('SHOW_UNIQUES', 1);  //Show unique user posts (1: yes  0: no)
define('NOT4CHAN', 0);      //uuh
define('NO_TEXTONLY', 1);
define('SAVE_XFF', 0);      //Log XFF request headers in MySQL database (1: yes  0: no)
define('UPDATE_THROTTLING', 0); //Board rendering limitation (1: yes  0: no)
define('CACHE_TTL', 1);     //Cache TTL for packets (1: yes  0: no)
define('GIF_ONLY', 0);      //Only allow GIF images (1: yes  0: no)
define('SHOW_SECONDS', 1);  //Show seconds on post timestamp (1: yes  0: no)
define('PROCESSLIST', 0);   //uuhhh
define('PROFILING', 0);     //uhh
define('USE_GZIP', 0);      //Compress static pages with .gz (1: yes  0: no)
define('SAGE_FILTER', 0);   //Disable sage function? (1: yes  0: no)
define('MAX_LINES', 128);
define('REPLIES_SHOWN', 5);
define('RAPIDSEARCH_LOGGING', 0);
define('SORT_NUMERIC', 1);
define('MAX_LINES_SHOWN', 128);
define('USE_SRC_CGI', 0);
define('SALTFILE', 'tripsalt');     //File for storing the secure trip salt
define('NAV_TXT', 'nav.txt');   //File for storing the header HTML
define('NAV2_TXT', 'nav2.txt'); //File for storing the footer HTML
define('STATIC_REBUILD', 0);    //Broken, don't use

//no php resource
define('ROBOT9000', 0); //robot9000.php
define('OEKAKI_BOARD', 0); //oekaki.php
define('JANITOR_BOARD', 0); //broomcloset.php
define('WORD_FILT', 0); //wf.php
define('USE_RSS', 0); //rss.php
define('SHOW_BLOTTER', 0); //blotter.php
define('BLOTTER_PATH', 'http://www.4chan.org/blotter.php');
define('BLOTTER_URL', 'http://www.4chan.org/blotter');

//ad blocks
define('BANROT_AD', 0);
define('TOPAD_TABLE', 0);
define('BANROT2_AD', 0);
define('BANROT_B', 0);
define('BOTTOM_AD', 0);
define('FIXED_TEXT_AD', 0);
define('LEADERBOARD_AD', 0);
define('LEADERBOARD_TABLE', 1);
define('LEADERBOARD_LINK', '');
define('LEADERBOARD_IMG', '');

//fun stuff
define('GLOBAL_MSG', '<center><font color="red"><b>testing</b></font></center>');
define('PARTY', 0);
define('FORTUNE_TRIP', 1);
define('DICE_ROLL', 1);
define('ENABLE_PDF', 1);
define('ENABLE_EXIF', 0);
?>
