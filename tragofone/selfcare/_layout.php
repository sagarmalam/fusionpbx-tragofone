<?php

function sc_render_header(array $session, string $active, string $title): void {
	global $sc_nonce; $theme = $session['theme'];
	$values = [
		'l_bg'=>$theme['l_bg'] ?? 'F7F8FA', 'l_fg'=>$theme['l_fg'] ?? '172033', 'l_btn'=>$theme['l_btn'] ?? '1769E0', 'l_btn_fg'=>$theme['l_btn_fg'] ?? 'FFFFFF',
		'd_bg'=>$theme['d_bg'] ?? '10141D', 'd_fg'=>$theme['d_fg'] ?? 'F4F7FB', 'd_btn'=>$theme['d_btn'] ?? '6EA8FE', 'd_btn_fg'=>$theme['d_btn_fg'] ?? '08101F',
	];
	foreach ($values as $key=>$value) { $values[$key] = tragofone_selfcare_theme::color($value); }
	$navigation = ['home'=>['Home','index.php','⌂'], 'calls'=>['Call handling','calls.php','↗'], 'voicemail'=>['Voicemail','voicemail.php','▶'], 'settings'=>['Settings','settings.php','⚙']];
	?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="color-scheme" content="light dark"><title><?= sc_escape($title) ?> · <?= sc_escape($theme['brand_name'] ?? 'Tragofone') ?></title><link rel="stylesheet" href="assets/selfcare.css"><style nonce="<?= sc_escape($sc_nonce) ?>">:root{--sc-bg:#<?= $values['l_bg'] ?>;--sc-fg:#<?= $values['l_fg'] ?>;--sc-button:#<?= $values['l_btn'] ?>;--sc-button-fg:#<?= $values['l_btn_fg'] ?>}@media(prefers-color-scheme:dark){:root{--sc-bg:#<?= $values['d_bg'] ?>;--sc-fg:#<?= $values['d_fg'] ?>;--sc-button:#<?= $values['d_btn'] ?>;--sc-button-fg:#<?= $values['d_btn_fg'] ?>}}</style></head><body>
<div class="sc-shell"><header class="sc-header"><div class="sc-brand"><?php if (!empty($theme['brand_logo'])) { ?><img src="<?= sc_escape($theme['brand_logo']) ?>" alt=""><?php } else { ?><span class="sc-brand-mark" aria-hidden="true">T</span><?php } ?><div><strong><?= sc_escape($theme['brand_name'] ?? 'Tragofone') ?></strong><span>Extension <?= sc_escape($session['extension']) ?></span></div></div></header>
<nav class="sc-navigation" aria-label="Self-care navigation"><?php foreach ($navigation as $key=>[$label,$url,$icon]) { ?><a href="<?= $url ?>" class="<?= $active===$key?'active':'' ?>" <?= $active===$key?'aria-current="page"':'' ?>><span aria-hidden="true"><?= $icon ?></span><b><?= $label ?></b></a><?php } ?></nav><main class="sc-main"><div class="sc-page-title"><h1><?= sc_escape($title) ?></h1></div><?php if ($message=sc_message()) { ?><div class="sc-alert <?= $message['type'] ?>" role="status"><?= sc_escape($message['text']) ?></div><?php } ?>
	<?php
}

function sc_render_footer(): void { ?></main></div></body></html><?php }
