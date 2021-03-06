<?php
if (version_compare(PHP_VERSION,'5','>='))
 require_once('php5_dom_wrapper.php'); //Load the PHP5 converter


class GamerCard
{
	var $memberType;

	var $tag;
	var $rep;
	var $score;
	var $zone;

	var $recentlyPlayed;
	var $recentlyPlayedImageUrls;

	var $gamerPictureUrl;


}

function error_handler($errno, $errstr)
{
}

function getGamerCard($gamerTag, $errors = 0)
{
	error_reporting($errors);
	$url = "http://gamercard.xbox.com/" . rawurlencode($gamerTag) . ".card";
	// Grab the gamercard - the "body" tag and its contents are a valid xml doc 
	// so we throw away the contents of the "head" tag. 
	// We don't need them anyway.
	
	if ($errors == 0 ) 
	{
		set_error_handler("error_handler");
	}
	else
	{
		print "Attempting to retrieve gamercard HTML from '$url'\n";
	}
	
	$cardHTML = file_get_contents($url);
	if ($errors == 0)
	{
		restore_error_handler();
	}
	
	if ($cardHTML == false)
	{
		return "";
	}
	$tmp = preg_split("/<body>/", $cardHTML);
	$tmp = preg_split("/<\/body>/", $tmp[1]);
	
	
	$cardHTML = $tmp[0];
	/*
	// New design has no javascript in it so this is commented out for speed.
	// Keep it here in case we need it again later.
	$cardHTML = preg_replace(array("@<script[^>]*?>.*?</script>@si", "@<noscript[^>]*?>.*?</noscript>@si"), array("", ""), $tmp[0]); // Strip SCRIPT tags if present - not valid XML
	*/

	$xml = domxml_open_mem("<?xml version='1.0' standalone='yes'?><body>" . $cardHTML . "</body>");
	if (!$xml)
	{
		return "";
	}

	$root = $xml->document_element();
	$xpath = $xml->xpath_new_context();

	$card = new GamerCard();
	$card->tag = $gamerTag;

// Membership type (Gold/Silver)
// I have decided not to care about this for now.
/*
	$obj = $xpath->xpath_eval('//div[@class="Gamertag"]/a/span');
	$nodeset = $obj->nodeset;
	if (!$nodeset)
	{
		return "";
	}

	$card->memberType = $nodeset[0]->get_attribute("class");
*/
// Gamer picture
	$obj = $xpath->xpath_eval('//img[@id="Gamerpic"]');
	$nodeset = $obj->nodeset;


	$card->gamerPictureUrl = $nodeset[0]->get_attribute("src");

// Gamerscore

	$obj = $xpath->xpath_eval('//div[@id="Gamerscore"]');
	$nodeset = $obj->nodeset; 
	$card->score = $nodeset[0]->get_content();

// Rep
/*
	// Man, screw Rep. Nobody cares.
	$obj = $xpath->xpath_eval('//span[preceding-sibling::span="Rep"]/img');
	$nodeset = $obj->nodeset;

	$url = $nodeset[0]->get_attribute("src");
	$tmp = preg_split("/[._]/", $url);
	$card->rep = $tmp[3];
*/

// Recently Played

	$obj = $xpath->xpath_eval('//ol[@id="PlayedGames"]//img');
	$nodeset = $obj->nodeset;
	
	foreach($nodeset as $node)
	{
		$card->recentlyPlayed[] = $node->get_attribute("title");
		$card->recentlyPlayedImageUrls[] = $node->get_attribute("src");
	}

	return $card;
}

if (defined("STDIN"))
{
	// Running from command line - get gamertag from arguments and dump data
	
	$tag = $argv[1];
	$card = getGamerCard($tag, E_ALL);
	
	print "Gamertag: $card->tag \n";
	print "Gamerscore: $card->score \n";
	var_dump($card->recentlyPlayed);
	
}
?>