<?php

$url = "https://github.com/sandreas/m4b-tool";
$html = file_get_contents($url);


libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML($html);

$xpath = new DOMXPath($doc);
$bodyNode = $xpath->query('//article')->item(0);

$readmeHtml = $doc->saveHTML($bodyNode);

$templateFile = __DIR__ . "/build-homepage-template.html";
$template = file_get_contents($templateFile);

$replacements = [
    "<div class=\"m4b-tool\">" => "<div class=\"m4b-tool\">" . $readmeHtml,
    "https://" => "//"
];

// $homepageHtml = str_replace("<div class=\"m4b-tool\">", "<div class=\"m4b-tool\">".$readmeHtml, $template);

$homepageHtml = strtr($template, $replacements);

file_put_contents(__DIR__ . "/../homepage/index.html", $homepageHtml);
