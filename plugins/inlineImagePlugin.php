<?php

/**
 * inlineImage Plugin v1.0a2
 * 
 * This plugin defines a placeholder that allows inline images to be inserted into
 * messages
 *
 * For more information about how to use this plugin, see
 * http://resources.phplist.com/plugins/inlineImage .
 * 
 */

/**
 * Registers the plugin with phplist
 * 
 * @category  phplist
 * @package   inlineImagePlugin
 */

class inlineImagePlugin extends phplistPlugin
{
    /*
     *  Inherited variables
     */
    public $name = 'Inline Image Plugin';
    public $version = '1.0a2';
    public $enabled = false;
    public $authors = 'Arnold Lesikar';
    public $description = 'Allows the use of inline images in messages';
    public $DBstruct =array (	//For creation of the required tables by Phplist
    		'image' => array(
    			"imgid" => array("integer not null primary key auto_increment","Image numerical ID"),
    			'owner' => array("integer not null", "ID of user who uploaded the image"),
    			'local_name' => array("varchar(255) not null","A unique local file name including extension"),
				"file_name" => array("varchar(255) not null","File name including extension"),
				"short_name" => array("varchar(255)", "Convenient name for image assigned by user"),
				"width" => array("integer not null", "The width of the image"),
				"height" => array("integer not null", "The height of the image"),
				"description" => array("Text","Description to be used in text message or in alt attribute"),
				"size" => array("integer not null", "File size in kilobytes"),
				"type" => array("char(50)", "MIME type of the image"),
				"cid" => array("char(32) not null","MIME content ID")
			),
			'msg' => array(
				"id" => array("integer not null", "Message ID"),
				"imgid" => array("integer not null","Image numerical ID"),
				"texttag" => array("Text", "Replacement for the placeholder in text messages"),
				"imagetag" => array("Text", "HTML image tag with attributes and cid")
			)
		);  				// Structure of database tables for this plugin
	public $tables = array ('image', 'msg');	// Table names are prefixed by Phplist
	public $numberPerList = 10;		// Number of images to be listed at once in table
	public $settings = array(
    		"ImageAttachLimit" => array (
      			'value' => 100,
      			'description' => "Limit for size of attached inline images in kB",
      			'type' => 'integer',
      			'allowempty' => 0,
      			"max" => 999,
      			"min" => 10,
      			'category'=> 'campaign',
   			 	)
   			 );
	private $processing_queue = false;
    private $cache;			// Keep inline image info while the queue is being processed
    private $curid;			// ID of the current message being processed
    public $image_types = array(
                  'gif'  => 'image/gif',
                  'jpg'  => 'image/jpeg',
                  'jpeg'  => 'image/jpeg',
                  'jpe'  => 'image/jpeg',
                  'bmp'  => 'image/bmp',
                  'png'  => 'image/png',
                  'tif'  => 'image/tiff',
                  'tiff'  => 'image/tiff'
            );
    
    public $topMenuLinks = array(
    			'ldaimages' => array('category' => 'campaigns')
    			); 
  	public $pageTitles = array('ldaimages' => 'Manage Inline Images');

     
    function adminmenu() {
    	return array ("ldaimages" => "Manage Inline Images");
  	}
  	
  	function cleanFormString($str) {
		return sql_escape(strip_tags(trim($str)));
	}
	
	function myFormStart($action, $additional) {
		$html = formStart($additional);
		preg_match('/action\s*=\s*".*"/Ui', $html, $match);
		$html = str_replace($match[0], 'action="' . $action .'"', $html);
		return $html;
	}

	function __construct()
    {
    	$this->processing_queue = false;
    	
		$this->coderoot = dirname(__FILE__) . '/inlineImagePlugin/';
		
		$imagedir = $this->coderoot . "images";
		if (!is_dir($imagedir))
			mkdir ($imagedir);
            	
		parent::__construct();
    }
    
    // The value of an attribute in an inline image placeholder
    // $str is the argument searched for the attribute
    // $att is the name of the attribute whose we are seeking
    // If $valueOnly is true, the value of the attribute is returned;
    // otherwise the entire attribute string is returned.
    private function getAttribute($str, $att, $valueOnly = 1) {
		$pat = '/' . $att . '\s*=\s*\S/i';
		preg_match ($pat, $str, $match);
		$char = substr($match[0],strlen($match[0])-1);
		switch ($char) {
			case "'":
				$pat = '/' . $att . "\s*=\s*'([^']*)'/i";
				break;
			case '"':
				$pat = '/' . $att . '\s*=\s*"([^"]*)"/i';
				break;
			default:
				$pat = '/' . $att . '\s*=\s*(\S*)/i';
		}
		preg_match ($pat, $str, $match);
		if ($valueOnly)
			return trim($match[1]);
		else
			return $match[0];
	}

	/* allowMessageToBeQueued
	* called to verify that the message can be added to the queue
	* @param array messagedata - associative array with all data for campaign
	* @return empty string if allowed, or error string containing reason for not allowing
	*/
	function allowMessageToBeQueued($messagedata = array()) 
	{
		$msg = $messagedata['message'];
		$owner = $_SESSION['logindetails']['id'];
		
		if (strpos($msg, '[IMAGE') === FALSE)  // If no image, nothing to do
    		return '';
    	
    	// Check that the brackets are closed for the inline image placeholders
    	preg_match_all('/\[IMAGE/', $msg, $match1);
    	preg_match_all('/\[IMAGE[^\]]+\]/', $msg, $match2);
    	if (count($match1[0]) != count($match2[0]))
    		return 'Brackets are not closed for an inline image placeholder.';
    	
    	$tblname = $GLOBALS['tables']['inlineImagePlugin_image'];
    	$total = 0;
    	foreach ($match2[0] as $val0) {
    		/* The editor encode HTML special characters. We must decode them
    		for searching with a regex. Also some spaces become '&nbsp; */
    		$val = htmlspecialchars_decode($val0, ENT_QUOTES | ENT_HTML401);
    		$val = str_replace('&nbsp;', ' ', $val);
    		
    		if (preg_match('/\(|\)/', $val) === false)	// If no parens, it's not our placeholder
    			continue;
    		
    		if ((!preg_match('/\[IMAGE\s*!?\s*\(/', $val)) || (!preg_match('/\)\s*\]/', $val)) ||
    			(substr_count($val, '(') > 1) || (substr_count($val, ')') > 1))
    			return "$val0 is a badly formed placeholder."; 
    		
    		preg_match('/\(\s*(\S.*)\s*(\)|\|)/U', $val, $match);
    		$src = trim($match[1]);
    		if (is_numeric($src)) {
    			$query = sprintf("Select size from %s where imgid=%d", $tblname, $src); 
    			if ( !isSuperUser())
    				$query .= sprintf(" and owner = '%s'", $owner);
    			$row = Sql_Fetch_Row_Query($query);
    			if (!$row)
    				return "Unknown image in $val0";
    			$total += $row[0];
    		} else {
    			$query = sprintf("Select size from %s where file_name='%s' or short_name='%s'" , $tblname, $src, $src);
    			if (!isSuperUser())
    				$query .= sprintf(" and owner = '%s'", $owner);
    			$row = Sql_Fetch_Row_Query($query);
    			if (!$row)
    				return "Unknown image in $val0";
    			if (count($row) > 1)
    				return "Ambiguous image specification in $val0";
    			$total += $row[0];
    		}
    	}
    	$lmt = getConfig("ImageAttachLimit");
    	if ($total > 1000 * $lmt)
    		return "Total size of inline images greater than the $lmt kB limit";
    	return '';
    }
    	
	/* messageQueued
	* called when a message is placed in the queue
	* @param integer id message id
	* @return null
	*
	* This is where we queue the inline images associated with the message.
	*/

	function messageQueued($id) {
		$imagetbl = $GLOBALS['tables']['inlineImagePlugin_image'];
		$msgtbl = $GLOBALS['tables']['inlineImagePlugin_msg'];
		$msgdata = loadMessageData($id);
		// In regex below, must account for editor's possible substitution of spaces by &nbsp;
    	preg_match_all('/\[IMAGE[^\(\)]*\([^\(\)]+\)(\s|&nbsp;)*\]/U', $msgdata['message'], $match); 
 	
		//Store everything needed for rapid processing of messages
		foreach ($match[0] as $val) {
			/* The editor encode HTML special characters. We must decode them
    		for searching with a regex. Also some spaces become '&nbsp; */
    		$val = htmlspecialchars_decode($val, ENT_QUOTES | ENT_HTML401);
    		$val = strip_tags($val); 	// The editor may insert tags into our placeholder
    		$val = str_replace('&nbsp;', ' ', $val);	// The editor seems to transform spaces into &nbsp; randomly
    		
    		preg_match('/\(\s*(\S.*)\s*(\)|\|)/U', $val, $match);
    		$src = trim($match[1]);
    		if (is_numeric($src)) 
    			$query = sprintf("Select imgid, cid, description from %s where imgid=%d", $imagetbl, $src);  			
    		else
    			$query = sprintf("Select imgid, cid, description from %s where (file_name='%s' or short_name='%s')" , $imagetbl, $src, $src);
    		if (!isSuperUser())
    			$query .= sprintf(" and owner = %d", $_SESSION["logindetails"]["id"]);
    		$row = Sql_Fetch_Row_Query($query);
    		$img = $row[0];
    		$cid = $row[1];
    		$desc = $row[2];
    		if (!preg_match('/\[IMAGE\s*!/', $val)) {	// '!' after [IMAGE flags no text replacement
    			$txt = '[IMAGE: ';
    			$alt = $this->getAttribute($val, 'alt');
				if ($alt != '')
					$txt .= $alt .']';
				else
					$txt .= $desc .']';
				$txt = strip_tags(HTML2Text($txt)); //HTML2Text produces wordwrap with <br /> at 70 chars! 			
    		} else
    			$txt = '';
    		
    		$attstr = preg_match('/\|(.*)\)/U', $val, $match)? trim($match[1]) : '';
			$imgtag ='<img src="cid:' . $cid . '"';
			if (($alt == '') && (strpos($attstr, 'alt') === false)) // Put in alt="...", 
																	// unless find alt="" explicitly among the attributes
				$imgtag .= ' alt="' . $desc . '"';
			$imgtag .= ' ' . trim($attstr) . '>';	
			
			$query = sprintf("insert into %s values (%d, '%d', '%s', '%s')", $msgtbl, $id, $img,
    			sql_escape($txt), sql_escape($imgtag));
			Sql_Query($query);
    	}
  	} 
  	
  	/*
	* campaignStarted
	* called when sending of a campaign starts
	* @param array messagedata - associative array with all data for campaign
	* @return null
	*
	* Here is where we cache the image data that will be used in constructing the message
	*/

  	function campaignStarted($messagedata = array()) {
  		$this->curid = $messagedata['id'];
  		$msgtbl = $GLOBALS['tables']['inlineImagePlugin_msg'];
  		$imgtbl = $GLOBALS['tables']['inlineImagePlugin_image'];
  		
		$query = sprintf('select texttag, imagetag, cid, type, file_name, local_name from %s natural join %s where id = %d', $msgtbl, $imgtbl, $this->curid);
  		$result = Sql_Query($query);
  		$i = 0;
  		while ($row = Sql_Fetch_Assoc($result)) {
  			$this->cache[$this->curid][$i] = $row;
  			$this->cache[$this->curid][$i]['contents'] = file_get_contents($row['local_name']);
  			$i++;
  		}
  	} 
  	
	/* 
   	* parseOutgoingTextMessage
   	* @param integer messageid: ID of the message
   	* @param string  content: entire text content of a message going out
   	* @param string  destination: destination email
   	* @param array   userdata: associative array with data about user
   	* @return string parsed content
   	*/
  	function parseOutgoingTextMessage($messageid, $content, $destination, $userdata = null) {
  		
  		// In regex below, must account for possible substitution of spaces by &nbsp;
    	preg_match_all('/\[IMAGE[^\(\)]*\([^\(\)]+\)(\s|&nbsp;)*\]/U', $content, $match); 
    	for ($i = 0; $i < sizeof($match[0]); $i++) { // And replace them
  				$content = str_replace($match[0][$i], $this->cache[$messageid][$i]['texttag'], $content);
  		}
    	return $content;
  	}

  	/* 
   	* parseOutgoingHTMLMessage
   	* @param integer messageid: ID of the message
  	* @param string  content: entire text content of a message going out
   	* @param string  destination: destination email
   	* @param array   userdata: associative array with data about user
   	* @return string parsed content
   	*/
  	function parseOutgoingHTMLMessage($messageid, $content, $destination, $userdata = null) {
  		
  		// In regex below, must account for possible substitution of spaces by &nbsp;
    	preg_match_all('/\[IMAGE[^\(\)]*\([^\(\)]+\)(\s|&nbsp;)*\]/U', $content, $match); 
    	for ($i = 0; $i < sizeof($match[0]); $i++) { // And replace them
  				$content = str_replace($match[0][$i], $this->cache[$messageid][$i]['imagetag'], $content);
  		}
    	return $content;
    }
  	
  	 /* processQueueStart
   	* called at the beginning of processQueue, after the process was locked
   	* @param none
   	* @return null
   	*/
  	function processQueueStart() {
  		$this->processing_queue = true;
  	}
  	
  	/* messageQueueFinished
   	* called when a sending of the queue has finished
   	* @return null
   	*/
  	function messageQueueFinished() {
  		$this->processing_queue = false;
  	}

	/**
   	* messageHeaders
   	*
	* return headers for the message to be added, as "key => val"
   	*
   	* @param object $mail
   	* @return array (headeritem => headervalue)
   	*/
  	function messageHeaders($mail) {
  	
  		if (!$this->processing_queue)	// Administrative message?
  			return;
  		
  		$imgs = $this->cache[$this->curid];
  		for($i=0; $i< sizeof($imgs); $i++) {
  		
printf("%s, %s, %s, %s\n", $imgs[$i]['cid'], $imgs[$i]['file_name'], $mail->encoding, $imgs[$i]['type']);
  			// Borrowed from the add_html_image() method of the PHPlistMailer class
  			if (method_exists($mail,'AddEmbeddedImageString')) {
        		$mail->AddEmbeddedImageString($imgs[$i]['contents'], $imgs[$i]['cid'], $imgs[$i]['file_name'], $mail->encoding, $imgs[$i]['type']);
      		} elseif (method_exists($mail,'AddStringEmbeddedImage')) {
        	## PHPMailer 5.2.5 and up renamed the method
        	## https://github.com/Synchro/PHPMailer/issues/42#issuecomment-16217354
        		$mail->AddStringEmbeddedImage($imgs[$i]['contents'], $imgs[$i]['cid'], $imgs[$i]['file_name'], $mail->encoding, $imgs[$i]['type']);
      		} elseif (isset($mail->attachment) && is_array($mail->attachment)) {
        	// Append to $attachment array
        		$cur = count($mail->attachment);
        		$mail->attachment[$cur][0] = $imgs[$i]['contents'];
        		$mail->attachment[$cur][1] = $imgs[$i]['file_name'];
        		$mail->attachment[$cur][2] = $imgs[$i]['file_name'];
        		$mail->attachment[$cur][3] = 'base64';
        		$mail->attachment[$cur][4] = $imgs[$i]['type'];
        		$mail->attachment[$cur][5] = true; // isStringAttachment
        		$mail->attachment[$cur][6] = "inline";
        		$mail->attachment[$cur][7] = $imgs[$i]['cid'];
      		} else {
        		logEvent("phpMailer needs patching to be able to use inline images");
       			print Error("phpMailer needs patching to be able to use inline images");
        	}
        } 
        return '';
  	}
	
}