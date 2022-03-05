<?php
/*
    HTMLics Band-Aid Construction Kit - Simple manual HTML-writing helper "macros" for PHP
	v0.1.5
	Copyright (C) 2018, Szabi Ludens
	License: CC-BY 4.0 - https://creativecommons.org/licenses/by/4.0/legalcode

    Of course you shouldn't, but if you somehow still find yourself writing HTML
    code directly from PHP, and a working template toolkit is out of reach, or
    it's just overkill for the task, then this is probably the best trade-off
    between effortless & readable code, simple & lightweight (parserless) runtime,
    and useful, common, simple HTML crafting features, like:

      * Sparing most of the dreadfully annoying-to-type "insect" chars (<="'&;/>).
        And especially close tags, needless to say.

      * Clutterless tag/attrib. shorthand names for simple stuff like BR, HR etc.

      * `load($file)` to include stuff as-is.

      ! `php_include($script)` to wrap PHP's `include` statement into a real
         function, returning the output of $script (not bool, as the original).

      ! Element sequences in the PHP source can be separated with both dots and
        commas (like with echo).
        That's quite a cool feature of this thing: you can freely copy-paste
        from/to any typical PHP code emitting or constructing HTML fragments,
        be it a long `echo` list or a chain of concatenations, and you can e.g.
        add that code, unchanged, to a parent tag via the same HTMLics tag "macro",
        regardless of the original syntax.

      ! Another coolness is the `combine(...$itemlist)` function that makes it
        possible to conditionally enable/disable parts of a long echo list! :-o
        (See the `..., (DEBUG ? combine(DIV(...), ...) : "")` part in the
        project-central example!)

      * Straightforward way to add attributes to elements (via a [name => val]
        array as the first argument) where needed -- and allowed.
        (This one won't necessarily spare too much typing, but I still find it
        useful, especially with repeated attrib lists, defined as consts. Also,
        while kept in arrays, those attributes can be trivially added, removed,
        changed by transformations before outputting.

      * Full control over the output. (Well, it's your echo, anyway. ;) )

      * Even "sloppy" tag support (for BODY, P, LI, DD etc. elements) that can be
        left unclosed (if used as `BODY(), DIV("thing...")` instead of
        `BODY(DIV("thing..."))`).
        (This just delegates the nested parenthesis-typing burden to browsers,
        with their ubiquitous heuristics for tolerating crappy HTML...)

    Other interesting points:

      * PHP Notice "undefined const x, assuming 'x'" explicitly intercepted for
        hard-core quote-sparing... ;) (E.g. you can type attr. names or most CSS
        class names unquoted. Hallelujah!)

      * No external dependencies. Just this one file, and you.

      * FP-like implementation: almost every function is "pure", consisting
        of one single `return` statement, with no variables, no assignments
        ("side effects") etc. (I have no idea if it's useful or not, but there
        you have it. ;) )

      * Easily extendable (not all the HTML elements are added by default, but
        you can immediately see how it's done, and add yours).

    Also:

      * Unfortunately not quite possible, but this is as close as it gets to
        avoiding string concatenations altogether, before outputting a HTML stream.
        Nevertheless, with the "functional" approach here, much of the temp.
        intermediate stages of building the DOM tree can be spared by representing
        a node tree as simple nested function calls. String snippets are stored
        on the stack; no need to chuck some HTML tags together into short-lived
        temporary variables, just to pass them around.

      * I suspect that the approach used here might lend itself to developing
        intermediate generated formats (not in this state, but extended, e.g.
        accepting not only strings, but actual node objects etc.), like ASTs.

    DISCLAIMER:

      * This script is meant to be used in trivial environments, not as the core
        HTML renderer of some production code! No effort has been made to bullet-
        proofing, perf. optimization, namespace- or composer-friendliness etc.
        You are on your own. (Well, it's web development; how much worse can
        it get, anyway?)

TODO:
- META stuff for HEAD (analogously to the attribs for elems.)
- CSS, JS (inclusion) helpers

*/

if (!defined('HTMLICS_DEBUG'))
      define('HTMLICS_DEBUG', defined('DEBUG') or isset($DEBUG)); // or whatever

if (!defined('HTMLICS_DEBUG_SHOWNOTICES'))
      define('HTMLICS_DEBUG_SHOWNOTICES', true);


//--------------------------------------
// Non-HTML-specific stuff...

// These are defined solely to avoid the typing burden of the quotes
// (i.g. the need to change shifting 3 times in such short strings).
const SP = " ";
const NL = "\n";
const CR = "\x0d";
const LF = "\x0a";
const CRLF = "\x0d\x0a";
const Q = '"';
const SQ = "'";
const DOT = ".";
const COLON = ":";
const COMMA = ",";
const DASH = "-";
const SLASH = "/";
const BACKSLASH = "\\";
const PIPE = "|";
// And so on... (I might add a few more later.)


// Debug internal follow-ups as soon as possible:
	if (HTMLICS_DEBUG) assert_options(ASSERT_BAIL, 1);
	const DEBUG_PRETTY_NL = (HTMLICS_DEBUG ? NL : "");


// HTML stuff...

const AMP = '&amp;';
const LT = '&lt;';
const GT = '&gt;';
const NBSP = '&nbsp;';
const NDASH = '&ndash;';
const MDASH = '&mdash;';
const LARR = '&larr;';
const RARR = '&rarr;';

// Common attrib names (just to spare the quoting annoyance:
const _class = "class";
const _id = "id";
const _style = "style";
const _href = "href";
const _title = "title";
const _onload = "onload";

// Tag shortcuts: these are rarely used with any attribs.:
const BR = "<br />" . DEBUG_PRETTY_NL;
const HR = "<hr />" . DEBUG_PRETTY_NL;


//======================================================================
// USER Hooks...
//======================================================================
function _call_hook_url_fixup($url) {
// Auto-translating convenient app-specific links to actual, well-formed 
// URLs may be impossible here (in a generic HTML context), so if any sort
// of processing is needed, that should be done by the application.
// (E.g. URL types like "example.com" and "example.ext" can't be distinguished,
// so even the innocent-looking "add missing scheme to domains" is already
// something that can't be reliably automated here.)

	return function_exists("htmlics_hook_url_fixup")
                          ? htmlics_hook_url_fixup($url) : $url;
}


//======================================================================
// API Utils...
//======================================================================
function attrs_to_html($attrs) {
	assert(is_array($attrs));
	$res = "";
	foreach ($attrs as $n => $v) {
		$res .=
			!$n ? ""
			    // attr may have no "=value" part:
			    : " $n" . (!$v ? "" : "=\"$v\"")
		;
	}
	return $res;
}

// "DOM" logic...
// (Kinda' part of the API, too...)

// This one just returns its imputs combined
function combine(...$content) {
// Currently ...$content can be empty, or a list of strings (i.e. then $content will be an array).
//!! Whether $content is indeed a list of strings (vs. other crap), is not checked.
//
	return	is_array($content)
			? implode($content) //! Here we could be *way* smarter than this...
			                    //! (E.g. loop over them and handle non-strings as e.g. DOM nodes.)
			: (is_string($content)
				? $content
				: (HTMLICS_DEBUG? " [??WTF is this '$content'??] " : "")
			  )
		;
}

function tag($tag, $attrs = [], ...$content) {
	return "<$tag" . attrs_to_html($attrs) . ">"
		 . combine(...$content) . "</$tag>"
	;
}
function sloppy_tag($tag, $attrs = [], ...$content) {
	return "<$tag" . attrs_to_html($attrs) . ">"
		. (empty($content) ? "" : (combine(...$content) . "</$tag>"))
	;
}
function void_tag($tag, $attrs = [])	{ return "<$tag" . attrs_to_html($attrs) . ">"; }
function sc_tag($tag, $attrs = [])	{ return "<$tag" . attrs_to_html($attrs) . "/>"; }


// Internal fixtures...

function _unpack_opt_attrs($f, ...$args) {
	return $f( (isset($args[0]) and is_array($args[0]))
					? array_shift($args)
					: [],
		 ...$args
	);
}

function _tag_with_opt_attrs($tag, ...$content) {
	return _unpack_opt_attrs( function($attrs, ...$content) use($tag) {
			return tag($tag, $attrs, ...$content);},
		...$content);
}
function _sloppy_tag_with_opt_attrs($tag, ...$content) {
	return _unpack_opt_attrs( function($attrs, ...$content) use($tag) {
			return sloppy_tag($tag, $attrs, ...$content);},
		...$content);
}


//======================================================================
// API...
//======================================================================

// Make PHP's include a function returning the output of the script:
function php_include($name) { ob_start(); include $name; return ob_get_clean(); }

function load($name) { return file_get_contents($name); }

// HTML construction
function DOCTYPE($doctype = "html") { return ($doctype ? void_tag("!DOCTYPE", [$doctype=>""]) : "")
						. DEBUG_PRETTY_NL; }

function HTML(...$content)	{ return sloppy_tag("html", [], ...$content); }

/*!! Earlier DOCTYPE was a pseudo-attr. of HTML. This is an example of how to handle that scheme.
function HTML(...$content)	{
// DOCTYPE can be specified via a pseudo-attr, as: HTML(['htmlics-doctype'=>"..."] ... )
// Default: "html". Setting it to "" disables emitting the DOCTYPE line.
				return _unpack_opt_attrs( function($attrs, ...$content) {
						if (isset(         $attrs['htmlics-doctype'])) {
							$doctype = $attrs['htmlics-doctype'];
							unset(     $attrs['htmlics-doctype']);
						} else {
							$doctype = "html"; //!
						}
						// Note: let's just add an NL regardless of HTMLICS_DEBUG.
						return ($doctype ? "<!DOCTYPE $doctype>".NL : "") .
							sloppy_tag("html", $attrs, ...$content);
					},
					...$content);
				}
!!*/

function HEAD(...$content)	{ return sloppy_tag("head", [], ...$content) . DEBUG_PRETTY_NL; }
function STYLE(...$css)		{ return _tag_with_opt_attrs("style", ...$css) . DEBUG_PRETTY_NL; }
function BODY(...$content)	{ return _sloppy_tag_with_opt_attrs("body", ...$content); }
function DIV(...$content)	{ return _tag_with_opt_attrs("div", ...$content); }
function SPAN(...$content)	{ return _tag_with_opt_attrs("span", ...$content); }
function P(...$content)		{ return _sloppy_tag_with_opt_attrs("p", ...$content) . DEBUG_PRETTY_NL; }

function A($url, ...$label)	{ return _unpack_opt_attrs( function($attrs, ...$label) use($url) {
						if (!$label) $label = [$url];
						$attrs['href'] = _call_hook_url_fixup($url); // let the app process URLs!
						return tag("a", $attrs, ...$label);
					},
					...$label);
				}
function Ah($url, ...$label)	{ if (!$label) $label = [$url]; return A("http://$url", ...$label); }
function Ahs($url, ...$label)	{ if (!$label) $label = [$url]; return A("https://$url", ...$label); }

function I(...$content)	{ return tag("i", [], ...$content); }
function B(...$content)	{ return tag("b", [], ...$content); }
function U(...$content)	{ return tag("u", [], ...$content); }
function BIG(...$content)	{ return _tag_with_opt_attrs("big", ...$content); }
function SMALL(...$content)	{ return _tag_with_opt_attrs("small", ...$content); }
function INS(...$content)	{ return _tag_with_opt_attrs("ins", ...$content); }
function DEL(...$content)	{ return _tag_with_opt_attrs("del", ...$content); }


function H1(...$content)	{ return _tag_with_opt_attrs("h1", ...$content) . DEBUG_PRETTY_NL; ; }
function H2(...$content)	{ return _tag_with_opt_attrs("h2", ...$content) . DEBUG_PRETTY_NL; ; }
function H3(...$content)	{ return _tag_with_opt_attrs("h3", ...$content) . DEBUG_PRETTY_NL; ; }
function H4(...$content)	{ return _tag_with_opt_attrs("h4", ...$content) . DEBUG_PRETTY_NL; ; }

function PRE(...$content)	{ return _sloppy_tag_with_opt_attrs("pre", ...$content); }
function CODE(...$content)	{ return _sloppy_tag_with_opt_attrs("code", ...$content); }
function UL(...$content)	{ return _tag_with_opt_attrs("ul", ...$content) . DEBUG_PRETTY_NL; ; }
function OL(...$content)	{ return _tag_with_opt_attrs("ol", ...$content) . DEBUG_PRETTY_NL; ; }
function LI(...$content)	{ return _sloppy_tag_with_opt_attrs("li", ...$content); }

function DL(...$content)	{ return _tag_with_opt_attrs("dl", ...$content) . DEBUG_PRETTY_NL; ; }
function DT(...$content)	{ return _sloppy_tag_with_opt_attrs("dt", ...$content); }
function DD(...$content)	{ return _sloppy_tag_with_opt_attrs("dd", ...$content); }

function TABLE(...$content)	{ return _tag_with_opt_attrs("table", ...$content) . DEBUG_PRETTY_NL; ; }
function TR(...$content)	{ return _tag_with_opt_attrs("tr", ...$content) . DEBUG_PRETTY_NL; ; }
function TH(...$content)	{ return _tag_with_opt_attrs("th", ...$content); }
function TD(...$content)	{ return _tag_with_opt_attrs("td", ...$content); }
function COLGROUP(...$content)	{ return _tag_with_opt_attrs("colgroup", ...$content); }
function COL(...$content)	{ return _sloppy_tag_with_opt_attrs("col", ...$content); }

set_error_handler(
	function(int $errno, string $errstr, string $errfile = null, int $errline = null, array $errcontext = null) {
		switch ($errno) {
		case 8: // undef. const/
			if (preg_match("/undefined constant.*assumed/", $errstr)) {
				if (HTMLICS_DEBUG and HTMLICS_DEBUG_SHOWNOTICES) {
					echo SPAN(['style'=>"border:1px solid gray; color: gray; padding: 0 3;"],
						SMALL(['class'=>"warning"], "PHP Notice: \"$errstr\"")
					);
				}
				return true; // skip the default handler
			}
		}
		return false; // proceed to the default handler
	},
	E_NOTICE
);
