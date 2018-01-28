<?php

define('DEBUG_REQ', isset($_GET['debug']) and $_GET['debug'] !== "0");
define('DEBUG_NOTICES_REQ', isset($_GET['debug-notices']) ? $_GET['debug-notices'] !== "0" : true);
const DEBUG = DEBUG_REQ;
const HTMLICS_DEBUG_SHOWNOTICES = DEBUG_NOTICES_REQ;

require_once "../htmlics.php";

// Custom addons:
function BLINK(...$content)	{ return _sloppy_tag_with_opt_args("span", [_class=>blink], ...$content); }
function REM($text)	{ return " <small><i>($text)</i></small>"; }

// Some cool shortcuts, just for fun:
const indent = [_class => "indent"];
const undent = [_class => "undent"];
const blink  = [_class => "blink"];
const red    = [_class => "red"];
const green  = [_class => "green"];
const blue   = [_class => "blue"];

const example_com = "http://example.com";


//--------------------------------------
// Go!...
//
echo //DOCTYPE(),
HTML(), HEAD( // or HTML(),HEAD(..., or HTML(HEAD(..., or just HEAD() or HEAD(...
STYLE('
body {
	margin: 0;
	padding: 1em;
	border-top: 40px solid #888;
	border-bottom: 40px solid #888;
	border-left: 60px solid #888;
	border-right: 60px solid #888;
	color: #202020;
	background: #c0c0c0;
	font-family: Arial, Helvetica, Sans Serif;
}

.indent { margin-left: 2em; }
.undent { margin-left: -2em; }

.test  { background: yellow; }
.red   { color: red; }
.green { color: green; }
.blue  { color: blue; }

.warning { color: #b08080; }

/* Blinking support; just use the class! */
.blink { animation: blinker 1s linear infinite; }
@keyframes blinker { 10% {opacity: 0.5;} }
')
),
BODY(),//[onload => "alert('no shit')"]),

(DEBUG ? combine(
	DIV(),
	DIV("hello from a DIV"),
	DIV([_style=>"color: red"], "now from a RED one"),
	DIV([_class => "green"], hello,SP, SPAN(red, bello),SP, SPAN(blue, cello)),
	DIV([_class => test], "test div, very exciting"),
	NULL
):""),

DIV(
H1("Lunatek Project Central"),
UL(indent, // Or just a DD() + no .undent for H3, if noone's looking! ;-p
  H3(undent, "Local project tree... "),
    LI( A(example_com, 'full project tree (tag: "ALL")'), REM("via FlexBrowser") ),
    LI( A(example_com, 'projects tagged as "www"'), REM('i.e. web sites, or those that have some other "web relevance"') ),
  H3(undent, "Local GitList..."),
    LI( A(example_com, 'local "master" (GIT bare) repos'), REM("lists all at /srv/git") ),
  H3(undent, "Mgmt./coord. services..."),
    LI( A(example_com, "Redmine for my personal projects"), REM("login: s/...") ),
    LI(), A(example_com, "Redmine for contract work"), REM("login: qw/..."),
NL
) // ul
), // div

HR,
"Other places with projects not tracked here yet:
local NAS + PC + standalone disks, GitHub, Instructables etc... The situation is rather grim. ;)",

HR,
A("?debug=0", blink, "DEBUG OFF"), COMMA, SP, A("?debug", blink, "DEBUG ON"), COMMA, SP, 
A("?debug&debug-notices=0", blink, "DEBUG ON, but Notices OFF"),

NULL // just a sentinel to avoid the syntax error after the last comma
;
