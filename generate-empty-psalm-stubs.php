#!/bin/env php
<?php

declare(strict_types=1);

if (!file_exists('psalm.xml')) {
	throw new RuntimeException('Unable to find psalm.xml config');
}

$config = simplexml_load_string(file_get_contents('psalm.xml'));
if (!$config instanceof SimpleXMLElement) {
	throw new RuntimeException('Unable to parse psalm.xml config');
}

foreach ($config->stubs->file as $child) {
	$path = (string)$child->attributes()['name'];
	if (!file_exists($path)) {
		touch($path);
	}
}
