<?php

if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly

$imgtbl = $GLOBALS['tables']['inlineImagePlugin_image'];
$editid = $_GET['eid'];
$query = sprintf('select file_name, short_name, description from %s where id=%d', $imgtbl, $editid);
$row = Sql_Fetch_Assoc_Query($query);

$iip = $GLOBALS['plugins']['inlineImagePlugin'];

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

$panel = new UIPanel("Edit Properties of Image" . $editid, $mypanel);
print($panel->display());
print '</form>';
