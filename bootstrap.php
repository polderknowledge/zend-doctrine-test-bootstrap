<?php

// include this via phpunit.xml, or copy and modify to your needs

// cd to project root
chdir(__DIR__ .'/../../..');

require 'vendor/autoload.php';

\PolderKnowledge\TestBootstrap\Bootstrap::getApplication();
