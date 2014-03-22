<?php
if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly

$iip = $GLOBALS['plugins']['inlineImagePlugin'];
$imgdir = $iip->coderoot . 'images/';
$imgtbl = $GLOBALS['tables']['inlineImagePlugin_image'];

if (isset($_FILES) && is_array($_FILES) && sizeof($_FILES) > 0) {
	if (!$_FILES['error']) {
		$type = strtolower(trim($_FILES['inlineimage']['type']));
		$filename = $_FILES['inlineimage']['name'];
		$is_image = in_array($type, $iip->image_types);
		if (!$is_image)
			Warn("Only an image file can be uploaded here! Please choose a different file.");
		else {
			$tmpfile = $_FILES['inlineimage']['tmp_name'];
        	$owner = $_SESSION['logindetails']['id'];
			$shortname = $iip->cleanFormString($_REQUEST['shortname']);
    		$desc = $iip->cleanFormString($_REQUEST['image_description']);
    		$fparts = pathinfo($filename);
    		$localfile = tempnam($imgdir,$fparts['filename']);
			unlink($localfile);  // We just want the name
			$localfile .= '.' . $fparts['extension'];
        	move_uploaded_file($tmpfile, $localfile);
        	if (is_file($localfile) && filesize($localfile)) {
          		$cid = md5(uniqid(rand(), true));
    			$query = sprintf("insert into %s (owner, local_name, file_name, short_name, description, type, cid) values (%d, '%s', '%s', '%s', '%s', '%s', '%s')", $imgtbl, $owner, $localfile, $filename, $shortname, $desc, $type, $cid);
    			if (!Sql_query($query))
    				Warn('Cannot insert image into the database!'); 
    		} else
    			Warn('Cannot move uploaded file to image directory!');
    	}
	} else 
		Warn ("Upload failed!");
}
$enctype = 'enctype="multipart/form-data"';
print formStart($enctype . ' name="inlineimageform" class="inlineimageplugin" id="inlineimageform" ');

$mypanel .= sprintf ('<h2>Upload an Image File</h2><div><strong>%s:</strong></div> <div><input name="shortname" size="30" maxlength="30"></div>','Short Name for Image (30 Chars Max)');

$mypanel .= sprintf ('<div><strong>%s:</strong><br /></div> <div><input name="image_description" id="image_description" size="85" maxlength="255"></div>','Description of Image (255 Chars Max)');

$mypanel .= sprintf  ('<div><strong>%s</strong><br /></div><div><input type="file" name="inlineimage"/>&nbsp;&nbsp;<input class="submit" type="submit" name="save" value="%s"/></div><br />','Image File','Upload File');

$panel = new UIPanel('Inline Image Files',$mypanel,'');
print $panel->display();
print('</form>');
