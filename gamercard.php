<?php
if (version_compare(PHP_VERSION,'5','>='))
 require_once('php5_dom_wrapper.php'); //Load the PHP5 converter

class HTTPRequest
{
   var $_fp;        // HTTP socket
   var $_url;        // full URL
   var $_host;        // HTTP host
   var $_protocol;    // protocol (HTTP/HTTPS)
   var $_uri;        // request URI
   var $_port;        // port
  
   // scan url
   function _scan_url()
   {
       $req = $this->_url;
      
       $pos = strpos($req, '://');
       $this->_protocol = strtolower(substr($req, 0, $pos));
      
       $req = substr($req, $pos+3);
       $pos = strpos($req, '/');
       if($pos === false)
           $pos = strlen($req);
       $host = substr($req, 0, $pos);
      
       if(strpos($host, ':') !== false)
       {
           list($this->_host, $this->_port) = explode(':', $host);
       }
       else
       {
           $this->_host = $host;
           $this->_port = ($this->_protocol == 'https') ? 443 : 80;
       }
      
       $this->_uri = substr($req, $pos);
       if($this->_uri == '')
           $this->_uri = '/';
   }
  
   // constructor
   function HTTPRequest($url)
   {
       $this->_url = $url;
       $this->_scan_url();
   }
  
   // download URL to string
   function DownloadToString()
   {
       $crlf = "\r\n";
      
       // generate request
       $req = 'GET ' . $this->_uri . ' HTTP/1.0' . $crlf
           .    'Host: ' . $this->_host . $crlf
           .    $crlf;
      
       // fetch
       $this->_fp = fsockopen(($this->_protocol == 'https' ? 'ssl://' : '') . $this->_host, $this->_port);
       fwrite($this->_fp, $req);
       while(is_resource($this->_fp) && $this->_fp && !feof($this->_fp))
           $response .= fread($this->_fp, 1024);
       fclose($this->_fp);
      
       // split header and body
       $pos = strpos($response, $crlf . $crlf);
       if($pos === false)
           return($response);
       $header = substr($response, 0, $pos);
       $body = substr($response, $pos + 2 * strlen($crlf));
      
       // parse headers
       $headers = array();
       $lines = explode($crlf, $header);
       foreach($lines as $line)
           if(($pos = strpos($line, ':')) !== false)
               $headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos+1));
      
       // redirection?
       if(isset($headers['location']))
       {
           $http = new HTTPRequest($headers['location']);
           return($http->DownloadToString($http));
       }
       else
       {
           return($body);
       }
   }
}

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

function getGamerCard($gamerTag)
{
	error_reporting(0);
	$url = "http://gamercard.xbox.com/" . urlencode($gamerTag) . ".card";
	// Grab the gamercard - the card HTML is not valid xml (the base tag is not closed) 
	// but the "body" tag and its contents are a valid xml doc so we throw away the contents of the "head" tag. 
	// We don't need them anyway.
	
//	$request = new HTTPRequest($url);
//	$cardHTML = $request->DownloadToString();
	set_error_handler("error_handler");
	$cardHTML = file_get_contents($url);
	restore_error_handler();
	if ($cardHTML == false)
	{
		return "";
	}
	$tmp = preg_split("/<\/head>/", $cardHTML);
	$tmp = preg_split("/<\/html>/", $tmp[1]);
	$cardHTML = preg_replace(array("@<script[^>]*?>.*?</script>@si", "@<noscript[^>]*?>.*?</noscript>@si"), array("", ""), $tmp[0]); // Strip SCRIPT tags - not valid XML


	$xml = domxml_open_mem("<?xml version='1.0' standalone='yes'?>" . $cardHTML);
	if (!$xml)
	{
		return "";
	}

	$root = $xml->document_element();
	$xpath = $xml->xpath_new_context();

	$card = new GamerCard();
	$card->tag = $gamerTag;

// Membership type (Gold/Silver)

	$elems = $xml->get_elements_by_tagname("h3");
	foreach($elems as $elem)
	{
		$class = $elem->get_attribute("class");

		if ($class == "XbcGamertagGold")
			$card->memberType = "Gold";
		else if ($class == "XbcGamertagSilver")
			$card->memberType = "Silver";
	}

// Gamer picture
	$obj = $xpath->xpath_eval('//img[@class="XbcgcGamertile"]');
	$nodeset = $obj->nodeset;
	if (!$nodeset)
	{
		return "";
	}


	$card->gamerPictureUrl = $nodeset[0]->get_attribute("src");

// Gamerscore

	$obj = $xpath->xpath_eval('//span[preceding-sibling::span/img[@alt="Gamerscore"]]');
	$nodeset = $obj->nodeset;
	$card->score = $nodeset[0]->get_content();

// Rep

	$obj = $xpath->xpath_eval('//span[preceding-sibling::span="Rep"]/img');
	$nodeset = $obj->nodeset;

	$url = $nodeset[0]->get_attribute("src");
	$tmp = preg_split("/[._]/", $url);
	$card->rep = $tmp[3];

// Zone

	$obj = $xpath->xpath_eval('//span[preceding-sibling::span="Zone"]');
	$nodeset = $obj->nodeset;
	$card->zone = $nodeset[0]->get_content();

// Recently Played

	$obj = $xpath->xpath_eval('//div[@class="XbcgcGames"]//img');
	$nodeset = $obj->nodeset;
	
	foreach($nodeset as $node)
	{
		$card->recentlyPlayed[] = $node->get_attribute("title");
		$card->recentlyPlayedImageUrls[] = $node->get_attribute("src");
	}

	return $card;
}

?>