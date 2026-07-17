<?php

$addon = rex_addon::get('statistics');

$file = rex_file::get(rex_path::addon('statistics', 'README.md'));
if (null === $file) {
	$file = '';
}

$body = rex_markdown::factory()->parse($file);

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('statistics_help'));
$fragment->setVar('body', $body, false);
echo $fragment->parse('core/page/section.php');
