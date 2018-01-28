<?php

require "../htmlics.php";

echo UL(
	LI("Parent script."),
	LI(php_include("_included.php"))
);
