<?php

if (!defined('PHPLISTINIT')) die(); ## avoid pages being loaded directly

$imgtbl = $GLOBALS['tables']['inlineImagePlugin_image'];
$editid = $_GET['eid'];
$query = sprintf('select short_name, description from %s where id=%d');
$row = Sql_Fetch_Row_Query($query);

print formStart('name="inlineimageEdit" class="inlineimageplugin" id="inlineimageEdit" ');
