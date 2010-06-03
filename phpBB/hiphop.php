<?php

define('IN_PHPBB', true);

$phpbb_root_path = './';
$phpEx = 'php';

$table_prefix = 'phpbb_';

define('HIPHOP_TPL_GEN', true);

include_once('./includes/functions.php');
include_once('./includes/constants.php');
include_once('./includes/template.php');
include_once('./includes/functions_template.php');

$template = new template();

$style_dir = opendir('./styles');

while ($style = readdir($style_dir))
{
  $tpl_dir = "./styles/$style/template";
  
  if (!file_exists($tpl_dir))
  {
    continue;
  }

  $template->set_custom_template($tpl_dir, $style);
  $template->cachepath = $phpbb_root_path . 'cache/tpl_' . str_replace('_', '-', $style) . '_';

  $tpl_h = opendir($tpl_dir);

  while($tpl = readdir($tpl_h))
  {
    if ($tpl[0] == '.')
    {
      continue;
    }

    $template->set_filenames(array(
      'body' => $tpl
     ));
     $template->assign_display('body');
  }

}

// Now for the admin style

$admin_dir = opendir('./adm/style');

$template->set_custom_template('./adm/style', 'admin');

while ($adm = readdir($admin_dir))
{
  if ($adm[0] == '.')
  {
    continue;
  }
   $template->set_filenames(array(
     'body' => $adm
    ));
    $template->assign_display('body');
}

$tpls = glob('./cache/*.php');

foreach($tpls as $tpl)
{
  file_put_contents($tpl, trim(str_replace('<?php', "\n<?php", file_get_contents($tpl))));
}