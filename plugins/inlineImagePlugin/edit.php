<?php

if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly

$imgtbl = $GLOBALS['tables']['inlineImagePlugin_image'];
$editid = $_GET['eid'];
$query = sprintf('select short_name, description from %s where id=%d');
$row = Sql_Fetch_Row_Query($query);

print formStart('name="inlineimageEdit" class="inlineimageplugin" id="inlineimageEdit" ');

$editform = sprintf ('<div><strong>%s:</strong><br /></div> <div><input name="needle" id="needle" size="50" maxlength="255"></div>','Enter Image ID, File Name, or Short Name');
$searchform .= '<input class="submit" type="submit" name="search" value="Search" />';
$searchpanel = new UIPanel("Search for Image", $searchform);
$sform = $searchpanel->display();
