<?php

if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly

$imgtbl = $GLOBALS['tables']['inlineImagePlugin_image'];
$editid = $_GET['eid'];
$query = sprintf('select file_name, short_name, description, local_name, width, height from %s where imgid=%d', $imgtbl, $editid);
$row = Sql_Fetch_Assoc_Query($query);

$wid = $row['width'];
$hgt = $row['height'];

if (($wid > 600) || ($hgt > 200)) {
	$factor = min(1, 600/$wid, 200/$hgt);
	$widd = round($factor * $wid);
	$hgtd = round($factor * $hgt);
	$warn = (($widd == $wid)? '' :'The image above is not shown full size');
} 

$fn = 'plugins/inlineImagePlugin/images/'. basename($row['local_name']);

$iip = $GLOBALS['plugins']['inlineImagePlugin'];

print("<img src=\"$fn\" width=\"$widd\" height=\"$hgtd\" style=\"display: block; margin:0px auto 20px auto; border: 4px solid gray; padding:5px; background-color:Silver; \">");
print("<p style=\"text-align:center; font-size:16px\"><strong>Width: $wid&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Height: $hgt</strong><br />
<span style=\"font-size:14px; color:red;\">$warn</span>");
print ($iip->myFormStart(PageURL2('ldaimages'), 'name="inlineimageEdit" class="inlineimageplugin" id="inlineimageEdit"'));

$mypanel = '';

$mypanel .= sprintf ('<div>&nbsp;<br /><strong>%s:</strong></div> <div><input name="shortname" size="30" maxlength="30" value="%s"><br />&nbsp;</div>', 'Short Name(30 Chars Max)', $row['short_name']);

$mypanel .= sprintf('<input type="hidden" name="imageid" value="%s">', $editid);

$fn = $row['file_name'];
$ext = pathinfo($fn, PATHINFO_EXTENSION);
$fn = pathinfo($fn, PATHINFO_FILENAME);

$mypanel .= sprintf ('<div><strong>%s:</strong></div> <div><input name="extension" type="hidden" value="%s"><input name="filename" size="30" maxlength="30" value="%s"><strong>.%s</strong><br />&nbsp;<br /></div>', 'File Name (Without extension, 30 Chars Max)',$ext, $fn, $ext);

$mypanel .= sprintf ('<div><strong>%s:</strong><br /></div> <div><textarea name="image_description" id="image_description" cols="85" rows="3">%s</textarea></div>','Description of Image (255 Chars Max)', $row['description']);

$mypanel .= sprintf('<div><input class="submit" onclick="window.location.href=\'%s\'" type="button" name="cancel" value="%s">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input class="submit" type="submit" name="update" value="%s"/></div><br />',PageURL2('ldaimages'), 'Cancel','Save');

$panel = new UIPanel("Edit Properties of Image " . $editid, $mypanel);
print($panel->display());
print '</form>';
