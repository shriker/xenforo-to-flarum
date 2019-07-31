<?php

use s9e\TextFormatter\Configurator;

$configurator = new Configurator;

$configurator->rootRules->enableAutoLineBreaks();

// ---Begin Flarum
// These are the bbcodes supported by Flarum
$configurator->BBCodes->addFromRepository('B');
$configurator->BBCodes->addFromRepository('I');
$configurator->BBCodes->addFromRepository('U');
$configurator->BBCodes->addFromRepository('S');
$configurator->BBCodes->addFromRepository('URL');
$configurator->BBCodes->addFromRepository('IMG');
$configurator->BBCodes->addFromRepository('EMAIL');
$configurator->BBCodes->addFromRepository('CODE');
$configurator->BBCodes->addFromRepository('QUOTE');
$configurator->BBCodes->addFromRepository('LIST');
$configurator->BBCodes->addFromRepository('DEL');
$configurator->BBCodes->addFromRepository('COLOR');
$configurator->BBCodes->addFromRepository('CENTER');
$configurator->BBCodes->addFromRepository('SIZE');
$configurator->BBCodes->addFromRepository('*');
// ---End Flarum

$emoticons = [
    ':)'  => '1F642',
    ':-)' => '1F642',
    ';)'  => '1F609',
    ';-)' => '1F609',
    ':D'  => '1F600',
    ':-D' => '1F600',
    ':('  => '2639',
    ':-(' => '2639',
    ':-*' => '1F618',
    ':P'  => '1F61B',
    ':-P' => '1F61B',
    ':p'  => '1F61B',
    ':-p' => '1F61B',
    ';P'  => '1F61C',
    ';-P' => '1F61C',
    ';p'  => '1F61C',
    ';-p' => '1F61C',
    ':?'  => '1F615',
    ':-?' => '1F615',
    ':|'  => '1F610',
    ':-|' => '1F610',
    ':o'  => '1F62E',
    ':lol:' => '1F602'
];

foreach ($emoticons as $code => $hex) {
    $configurator->Emoji->aliases[$code] = html_entity_decode('&#x'.$hex.';');
}

$sites = ['bandcamp', 'dailymotion', 'facebook', 'indiegogo', 'instagram', 'kickstarter', 'liveleak', 'soundcloud', 'twitch', 'twitter', 'vimeo', 'vine', 'wshh', 'youtube'];
foreach ($sites as $siteId) {
    $configurator->MediaEmbed->add($siteId);
    $configurator->BBCodes->add($siteId, ['contentAttributes' => ['id', 'url']]);
}

$configurator->Autoemail;
$configurator->Autolink;

extract($configurator->finalize());
$configurator->saveBundle('XenForoBundle', 'xfscripts/XenForoBundle.php');
