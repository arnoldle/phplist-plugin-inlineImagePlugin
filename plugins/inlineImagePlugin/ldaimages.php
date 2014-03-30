<?php
if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly

/* For paging the image file listing */
if (isset($_GET["start"])){
   $start = sprintf("%d", $_GET["start"]);
}
else $start = 0;

if (isset($_POST['save']) || isset($_POST['search']) || isset($_POST['update'])){  	// We do deletion with a GET, so can't verify token then.

   /* check the XSRF token */
   if (!verifyToken()) {
     print Error(s('Invalid security token, please reload the page and try again'));
     return;
   }
}

$ourpage = $_GET['page'];
$ourname = $_GET['pi'];
$iip = $GLOBALS['plugins']['inlineImagePlugin'];
$imgdir = $iip->coderoot . 'images/';
$imgtbl = $GLOBALS['tables']['inlineImagePlugin_image'];

$currentUser = $_SESSION["logindetails"]["id"];
$needle = '';

// Handle image deletion
if (isset($_GET['delete'])) {
	$delid = $_GET['delete'];
	$query = sprintf("select local_name from %s where imgid=%d", $imgtbl, $delid);
	$row = Sql_Fetch_Assoc_Query($query);
	if ($row) {
		$thefile = $row['local_name'];
		unlink ($thefile); 	// Delete file from directory as well as database
		$query = sprintf("delete from %s where imgid=%d", $imgtbl, $delid);
		Sql_Query($query);
	} else
		Warn ('Cannot delete image id = ', $delid); 
}

// Handle image update
if (isset($_POST['update'])) {
	$fn = $iip->cleanFormString($_POST['filename']) . '.' . $iip->cleanFormString($_POST['extension']);
	$desc = substr($iip->cleanFormString($_POST['image_description']), 0, 255);
	$query = sprintf("update %s set file_name='%s', short_name='%s', description='%s' where imgid=%d", $imgtbl, $fn, $iip->cleanFormString($_POST['shortname']),$desc, $_POST['imageid']);
	if (!Sql_query($query))
		Warn(sprintf("Update of information for image %d failed!", $_POST['imageid']));

}

// Initialize seartch
if (isset($_POST['search']) || (isset($_POST['save']) && isset($_POST['needle']))){
	$needle = $_POST['needle'];
}

// Handle file upload
if (isset($_POST['save']) && isset($_FILES) && is_array($_FILES) && (sizeof($_FILES) > 0) && ($_FILES['inlineimage']['size'] > 0)) {
	if (!$_FILES['error']) {
		$type = strtolower(trim($_FILES['inlineimage']['type']));
		$typeErr = "Only an image file can be uploaded here! Please choose a different file.";
		$tmpfile = $_FILES['inlineimage']['tmp_name'];
		$filename = $_FILES['inlineimage']['name'];
		$is_image = in_array($type, $iip->image_types);
		if (!$is_image)
			Warn($typeErr);
		if (class_exists('finfo')) {
			$info = new finfo(FILEINFO_MIME);
			$tempary = explode(';', $info->file($tmpfile));
			$type = $tempary[0];
			$is_image = in_array($type, $iip->image_types);
			if (!$is_image)
				Warn($typeErr);
		} elseif (function_exists('mime_content_type')) {
			$type = mime_content_type ($tmpfile);
			$is_image = in_array($type, $iip->image_types);
			if (!$is_image)
				Warn($typeErr);
			}
		if ($is_image) {
			$owner = $_SESSION['logindetails']['id'];
			$shortname = $iip->cleanFormString($_REQUEST['shortname']);
    		$desc = $iip->cleanFormString($_REQUEST['image_description']);
    		$info = getimagesize($tmpfile);
    		$width = $info[0];
    		$height = $info[1];
    		$fparts = pathinfo($filename);
    		$localfile = tempnam($imgdir,$fparts['filename']);
			unlink($localfile);  // We just want the name, not the file, since we're modifying the name
			$localfile .= '.' . $fparts['extension'];
        	//move_uploaded_file($tmpfile, $localfile);
        	
        	/* Have copied the file move from the 'attachments section of 'send_core.php'
        	The use of move_uploaded_file function seems to be pretty easy to screw up.
        	Don't want to chance it. The Phplist approach seems to work in a lot of 
        	different environments. So use it here, instead of something original. */
 			
 			$newtmpfile = $filename.time();
        	move_uploaded_file($tmpfile, $GLOBALS['tmpdir'].'/'. $newtmpfile); 
        	// But we copy the our temporary file to the desired directory ourselves
        	if (is_file($GLOBALS['tmpdir'].'/'.$newtmpfile) && filesize($GLOBALS['tmpdir'].'/'.$newtmpfile)) {
          		$tmpfile = $GLOBALS['tmpdir'].'/'.$newtmpfile;
       		} else 
       			$tmpfile = '';
       		if ($tmpfile && filesize($tmpfile) && $tmpfile != "none") {
        		$file_size = filesize($tmpfile);
        		$fd = fopen( $tmpfile, "r" );
        		$contents = fread( $fd, filesize( $tmpfile ) );
        		fclose( $fd );
        		if ($file_size) {
         			$fd = fopen($localfile, "w" );
          			fwrite( $fd, $contents );
          			fclose( $fd );
          		}
        		if (is_file($localfile) && filesize($localfile)) {
          			$cid = md5(uniqid(rand(), true));
    				$query = sprintf("insert into %s (owner, local_name, file_name, short_name, width, height, description, type, cid) values (%d, '%s', '%s', '%s', %d, %d, '%s', '%s', '%s')", $imgtbl, $owner, $localfile, $filename, $shortname, $width, $height, $desc, $type, $cid);
    				if (!Sql_query($query))
    					Warn('Cannot insert image into the database!'); 
    			} else
    				Warn('Cannot move uploaded file to image directory!');
    		}
    	}
	} else 
		Warn ("Upload failed!");
}

// Create the file upload form
$enctype = 'enctype="multipart/form-data"';
print formStart($enctype . ' name="inlineimageform" class="inlineimageplugin" id="inlineimageform" ');

$mypanel .= sprintf ('<h2>Upload an Image File</h2><div><strong>%s:</strong></div> <div><input name="shortname" size="30" maxlength="30"></div>','Short Name for Image (30 Chars Max)');

$mypanel .= sprintf ('<div><strong>%s:</strong><br /></div> <div><input name="image_description" id="image_description" size="85" maxlength="255"></div>','Description of Image (255 Chars Max)');

$mypanel .= sprintf  ('<div><strong>%s</strong><br /></div><div><input type="file" name="inlineimage"/>&nbsp;&nbsp;<input class="submit" type="submit" name="save" value="%s"/></div><br />','Image File','Upload File');

// Create the search form
$searchform = sprintf ('<div><strong>%s:</strong><br /></div> <div><input name="needle" id="needle" size="50" maxlength="255"></div>','Enter Image ID, File Name, or Short Name');
$searchform .= '<input class="submit" type="submit" name="search" value="Search" />';
$searchpanel = new UIPanel("Search for Image", $searchform);
$sform = $searchpanel->display();

/* Prepare to list the image files */
$mylist = new WebblerListing("ID");
$qstr = "select imgid, ";
$cstr = "select count(*) from %s ";
if (!$needle) {  	// Get all the appropriate files
	if (isSuperUser()) 
		$qstr .= "owner, ";	// Only the superuser gets to see everyone's files
	$qstr .= "file_name, short_name, description, width, height from %s ";
	if (!isSuperUser()) {
		$qstr .= "where owner = %d order by imgid";
		$cstr .= "where owner = %d";
		$query = sprintf($qstr, $imgtbl, $currentUser);
		$cquery = sprintf($cstr, $imgtbl, $currentUser);
	} else {
		$qstr .= "order by imgid";
		$query = sprintf($qstr, $imgtbl);
		$cquery = sprintf($cstr, $imgtbl);
		}
} else {	// Get the files found in the search
	if (isSuperUser())
		$qstr .= "owner, ";
	$qstr .= "file_name, short_name, description, width, height from %s ";
	if (is_numeric($needle)) {
		$temp = "where imgid = %d";
		$qstr .= $temp;
		$cstr .= $temp;
		if (!isSuperUser()) {
			$temp =" and owner = %d";
			$qstr .= $temp;
			$cstr .= $temp;
			$query = sprintf($qstr, $imgtbl, $needle, $curuser);
			$cquery = sprintf($cstr, $imgtbl, $needle, $curuser);
		} else {
			$query = sprintf($qstr, $imgtbl, $needle);
			$cquery = sprintf($cstr, $imgtbl, $needle);
		}
	} else {
		$temp = "where (file_name='%s' or short_name='%s')";
		$qstr .= $temp;
		$cstr .= $temp;
		if (!isSuperUser()) {
			$temp = " and owner = %d order by imgid";
			$qstr .= $temp;
			$cstr .= $temp;
			$query = sprintf($qstr, $imgtbl, $needle, $needle, $curuser);
			$cquery = sprintf($cstr, $imgtbl, $needle, $needle, $curuser);
		} else {
			$qstr .= " order by imgid";
			$query = sprintf($qstr, $imgtbl, $needle, $needle);
			$cquery = sprintf($cstr, $imgtbl, $needle, $needle);
		}
	}
}

/* List the image files */
$temp = Sql_Fetch_Row_Query($cquery);
$total = $temp[0];
if (($total) && ($start >= $total))
	$start = $total - $iip->numberPerList;
$query .= ' limit ' . $start . ',' . $iip->numberPerList;
$dbresult = Sql_Query($query);

if (!$total)
	if (isset($_POST['needle']))
		$mylist->addElement('<strong>No images found</strong>', '');
	else
		$mylist->addElement('<strong>No images are available</strong>', '');
else {
	while ($row = Sql_Fetch_Assoc($dbresult)) {
		$pid = $row['imgid'];
		$editurl = PageURL2('edit','','eid=' . $pid);
		$mylist->addElement($pid, $editurl);
		if (isSuperUser())
			$mylist->addColumn($pid, 'Owner', $row['owner']);
		$mylist->addColumn($pid, 'Short Name', $row['short_name'], $editurl);
		$mylist->addColumn($pid, 'File Name', $row['file_name'], $editurl);
		$desc = $row['description'];

		if (strlen($desc) > 40)
			$desc = substr($desc, 0, 40) . '&hellip;';
		$desc .= '<br />' . sprintf('<span style="font-size:11px;"><strong>Width:</strong>&nbsp;%d&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>Height:</strong>&nbsp;%d</span>',$row['width'], $row['height']);

		$mylist->addColumn($pid, 'Description', $desc);
		$mydel = sprintf('<a href="javascript:deleteRec(\'%s\');" class="del">del</a>',"./?page=" . $ourpage . "&pi=" . $ourname . "&delete=" . $pid);
		$mylist->addColumn($pid, '', $mydel);
	}
	$paging=simplePaging("ldaimages", $start, $total, $iip->numberPerList,'Images');
	$mylist->usePanel($paging);

}

$list = $mylist->display(0,'myclass');
$ltitle = '<div class="panel"><div class="header"><h2>ID</h2></div>';
if (isset($_REQUEST['needle']))
	$newtitle = '<div class="panel"><div class="header"><h2>Images Found</h2></div>';
else
	$newtitle = '<div class="panel"><div class="header"><h2>Available Inline Images</h2></div>';
$list = str_replace($ltitle, $newtitle, $list);

$mypanel .= $sform . '<br />';
$mypanel .= $list . '<br />';
if (!is_dir($imgdir))
	Warn("Image directory does not exist! CANNOT UPLOAD FILES!");
else
	Info('Click on the ID, Short Name, or File Name of an image to see it or edit its properties.<br />Click on the accompanying trash can to delete an image');
$panel = new UIPanel('Inline Image Files',$mypanel,'');
print $panel->display();
print('</form>');
