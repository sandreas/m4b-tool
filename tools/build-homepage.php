<?php
// https://github.com/erusev/parsedown
require_once __DIR__ . "/Parsedown.php";

$templateFile = __DIR__ . "/build-homepage-template.html";
$readmeFile = __DIR__ . "/../README.md";

$template = file_get_contents($templateFile);
$readme = file_get_contents($readmeFile);

$p = new Parsedown();
$readmeHtml = $p->parse($readme);

$replacements = [
    "<div class=\"m4b-tool\">" => "<div class=\"m4b-tool\">" . $readmeHtml,
    "https://" => "//"
];

// $homepageHtml = str_replace("<div class=\"m4b-tool\">", "<div class=\"m4b-tool\">".$readmeHtml, $template);

$homepageHtml = strtr($template, $replacements);

file_put_contents(__DIR__ . "/../homepage/index.html", $homepageHtml);
