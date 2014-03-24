<?php

/**
 * inlineImage Plugin v1.0a1
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
    public $version = '1.0a1';
    public $enabled = false;
    public $authors = 'Arnold Lesikar';
    public $description = 'Allows the use of inline images in messages';
    public $DBstruct =array (	//For creation of the required tables by Phplist
    		'image' => array(
    			"id" => array("integer not null primary key auto_increment","Image numerical ID"),
    			'owner' => array("integer not null", "ID of user who uploaded the image"),
    			'local_name' => array("varchar(255) not null","A unique local file name including extension"),
				"file_name" => array("varchar(255) not null","File name including extension"),
				"short_name" => array("varchar(255)", "Convenient name for image assigned by user"),
				"description" => array("Text","Description to be used in text message or in alt attribute"),
				"type" => array("char(50)", "MIME type of the image"),
				"cid" => array("char(32) not null","MIME content ID")
			),
			'msg' => array(
				"id" => array("integer not null", "Message ID"),
				"placeholder" => array("Text", "The placeholder to be used in a search of the message."),
				"texttag" => array("Text", "Replacement for the placeholder in text messages"),
				"imagetag" => array("Text", "HTML image tag with attributes and cid"),
				"cid" => array("char(32) not null","Content ID of one of the inline images attached to the message"),
				"image_type" => array("char(50)", "MIME type of the image"),
				"file_name" => array("varchar(255) not null","File name including extension")
			)
		);  				// Structure of database tables for this plugin
	public $tables = array ('image', 'msg');	// Table names are prefixed by Phplist
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
		return strip_tags(htmlentities(trim($str)));
	}

	function __construct()
    {
    	// We want the image types listed in the PHPlistmailer class
		// We can get this list only by instantiating the class temporarily
		
		$this->coderoot = dirname(__FILE__) . '/inlineImagePlugin/';
            	
		parent::__construct();
    }
    
    // Check that quotes around attributes balance
    private function checkQuotes ($str)
    {
    	preg_match_all ("/\"|'/", $str, $match);
    	$quotes = $match[0];
    	$n = count($quotes);
    	$sought="";
    	$ptr = 0;
    	while ($ptr < $n) {
    		if ($sought != "") {
    			if ($quotes[$ptr] == $sought) 
    				$sought = "";   		
    		} else
    			$sought = $quotes[$ptr];
    		$ptr++;		
    	}
    	return ($sought=='');
    }
/***** Use the global tables[] for the table name in database access below ***********/    
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
		
	private function deleteImage($id) {
		$tblname = $GLOBALS['table_prefix'] . 'inline_image';
		$query = sprintf ("delete from %s where id = %d", $tblname, $id);
		Sql_Query($query);
	}
    
	/* allowMessageToBeQueued
	* called to verify that the message can be added to the queue
	* @param array messagedata - associative array with all data for campaign
	* @return empty string if allowed, or error string containing reason for not allowing
	*/
	function allowMessageToBeQueued($messagedata = array()) 
	{
		$msg = $messagedata['message'];
		if (strpos($msg, '[IMAGE') === FALSE)  // If no image, nothing to do
    		return '';
    	
    	// Check that the brackets are closed for the inline image placeholders
    	preg_match_all('/\[IMAGE/U', $msg, $match1);
    	preg_match_all('/\[IMAGE[^\]]+\]/U', $msg, $match2);
    	if (count($match1[0]) != count($match2[0]))
    		return 'Brackets are not closed for an inline image placeholder.';
    	
    	// Check that we have the image in the database
    	$tblname = $GLOBALS['table_prefix'] . 'inline_image';
    	foreach ($match2[0] as $val) {
    		if (!$this->checkQuotes($val))
    			return "Unbalanced quotes in $val";
    		$src = $this->getAttribute($val, 'src');
    		if (is_numeric($src)) {
    			$query = sprintf("Select cid from %s where id=%d", $tblname, $src); 
    			if (!Sql_Query($query))
    				return "Unknown image in $val";   			
    		} else {
    			$query = sprintf("Select file_name from %s where file_name=%s or short_name=%s" , $tblname, $src, $src);
    			$res = Sql_Query($query);
    			if (!$res)
    				return "Unknown image in $val";
    			if (Sql_Num_Rows($res) > 1)
    				return "Ambiguous image specification in $val";
    			$row = Sql_Fetch_Row($res);
    			$xtn = $this->getExtension($row[0]);
    			if (!array_key_exists($xtn, $this->image_types))  // Should try to use finfo to verify file is an image!
    				return "Invalid file extension for image in $val";
    		}
    	}
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
		$imagetbl = $GLOBALS['table_prefix'] . 'inline_image';
		$msgtbl = $GLOBALS['table_prefix'] . 'msg_image';
		$msgdata = loadMessageData($id);
		preg_match_all('/\[IMAGE[^\]]+\]/U', $msg, $match);
		
		// Store the message id, the notext flag, the form of the placeholder,
		// the attribute string, and the cid for each image in the message 
		foreach ($match[0] as $val) {
			$alt = $this->getAttribute($val, 'alt');
			$src = $this->getAttribute($val, 'src');
    		if (is_numeric($src)) 
    			$query = sprintf("Select cid, description, file_name from %s where id=%d", $imagetbl, $src);  			
    		else
    			$query = sprintf("Select cid, description, file_name from %s where file_name=%s or short_name=%s" , $tblname, $src, $src);
    		$row = Sql_Fetch_Row_Query($query);
    		$cid = $row[0];
    		$desc = $row[1];
    		$fn = $row[2];
    		$imgtyp = $this->image_types[$this->getExtension($fn)];
    		if (strpos($val, 'NOTEXT') != FALSE) {
    			$txt = '[IMAGE: ';
				if ($alt != '')
					$txt .= $alt .']';
				else
					$txt .= $desc .']';			
    		} else
    			$txt = '';
    		$attstr = str_replace('[IMAGE ', '', $val);
    		$attstr = str_replace('NOTEXT ', '', $attstr);
    		$attstr = trim(substr($attstr, 0, -1));
    		$attstr = trim(str_replace($this->getAttribute($attstr, 'src', 0), '', $attstr)); 
			$imgtag ='<img src="cid:' . $cid . '"';
			if ($alt = '')
				$imgtag .= ' alt="' . $desc . '"';
			$imgtag .= ' ' . $attstr . '>';	
    		$query = sprintf("insert into %s values (%d, %s, %s, %s, %s, %s, %s)", $msgtbl, $id, $val, $txt, $imgtag, $cid, $imgtyp, $fn);
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
  		$msgtbl = $GLOBALS['table_prefix'] . 'msg_image';
  		$query = sprintf('select placeholder, texttag, imagetag, cid, image_type, file_name from %s where id = %d', $msgtbl, $this->curid);
  		$result = Sql_Query($query);
  		$i = 0;
  		while ($row = Sql_Fetch_Array($result)) {
  			$this->cache[$this->curid][$i] = $row;
  			$this->cache[$this->curid][$i]['contents'] = file_get_contents($this->coderoot . 'images' . $row[5]);
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
  		foreach ($this->cache[$messageid] as $val) 
  			$content = str_replace($val['placeholder'], $val['texttag'], $content);
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
    	foreach ($this->cache[$messageid] as $val) 
  			$content = str_replace($val['placeholder'], $val['imagetag'], $content);
  		return $content;
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
  		foreach ($this->cache[$this->curid] as $val) {
  		
  			// Borrowed from the add_html_image() method of the PHPlistMailer class
  			if (method_exists($mail,'AddEmbeddedImageString')) {
        		$mail->AddEmbeddedImageString($val['contents'], $val['cid'], $val['file_name'], $mail->encoding, $val['image_type']);
      		} elseif (method_exists($mail,'AddStringEmbeddedImage')) {
        	## PHPMailer 5.2.5 and up renamed the method
        	## https://github.com/Synchro/PHPMailer/issues/42#issuecomment-16217354
        		$mail->AddStringEmbeddedImage($val['contents'], $val['cid'], $val['file_name'], $mail->encoding, $val['image_type']);
      		} elseif (isset($mail->attachment) && is_array($mail->attachment)) {
        	// Append to $attachment array
        		$cur = count($mail->attachment);
        		$mail->attachment[$cur][0] = $val['contents'];
        		$mail->attachment[$cur][1] = $val['file_name'];
        		$mail->attachment[$cur][2] = $val['file_name'];
        		$mail->attachment[$cur][3] = 'base64';
        		$mail->attachment[$cur][4] = $val['image_type'];
        		$mail->attachment[$cur][5] = true; // isStringAttachment
        		$mail->attachment[$cur][6] = "inline";
        		$mail->attachment[$cur][7] = $val['cid'];
      		} else {
        		logEvent("phpMailer needs patching to be able to use inline images");
       			print Error("phpMailer needs patching to be able to use inline images");
        	}
        }
        return '';
  	}
	
}