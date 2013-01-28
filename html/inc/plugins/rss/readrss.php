<?php

/* $Id$ */

/*******************************************************************************

 LICENSE

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.

 To read the license please visit http://www.gnu.org/copyleft/gpl.html

*******************************************************************************/

/******************************************************************************/

// general function
require_once('inc/generalfunctions.php');

// readrss functions
require_once('inc/plugins/rss/functions.readrss.php');

// require
require_once("inc/plugins/rss/lastRSS.php");

require_once('inc/classes/singleton/Configuration.php');
$cfg = Configuration::get_instance()->get_cfg();

// Just to be safe ;o)
if (!defined("ENT_COMPAT")) define("ENT_COMPAT", 2);
if (!defined("ENT_NOQUOTES")) define("ENT_NOQUOTES", 0);
if (!defined("ENT_QUOTES")) define("ENT_QUOTES", 3);

// THIS SHOULD BE EXTENDED FROM PLUGIN CLASS!!!
class RssReader
{
	private $rss_list;
	private $cfg;
	
	function __construct() 
	{
		require_once('inc/classes/singleton/Configuration.php');
		$this->cfg = Configuration::get_instance()->get_cfg();
	}
	
	function buildRssItemsArray()
	{
		
		// Get RSS feeds from Database
		$arURL = GetRSSLinks();
		
		// create lastRSS object
		$rss = new lastRSS();
		
		// setup transparent cache
		$cacheDir = $this->cfg['rewrite_rss_cache_path'];
		
		if (!checkDirectory($cacheDir, 0777)) {
			//@error("Error with rss-cache-dir", "index.php?page=index", "", array($cacheDir));
			print("The rss_cache_path does not exist: " . $this->cfg['rewrite_rss_cache_path']);
			exit();
		}
		$rss->cache_dir = $cacheDir;
		$rss->cache_time = $this->cfg["rewrite_rss_cache_min"] * 60; // 1200 = 20 min.  3600 = 1 hour
		$rss->strip_html = false; // don't remove HTML from the description
		$rss->CDATA = 'strip'; // TODO: these variables should be defined by default in lastRSS. Some of them are used in the code but not initialized by the class
		
		// set vars
		// Loop through each RSS feed
		$rss_list = array();
		foreach ($arURL as $rid => $url) {
			if (isset($_REQUEST["debug"]))
				$rss->cache_time=0;
			$rs = $rss->Get($url);
			if ($rs !== false) {
				if (!empty( $rs["items"])) {
					// Check this feed has a title tag:
					if (!isset($rs["title"]) || empty($rs["title"]))
						$rs["title"] = "Feed URL ".htmlentities($url, ENT_QUOTES)." Note: this feed does not have a valid 'title' tag";
		
					// Check each item in this feed has link, title and publication date:
					for ($i=0; $i < count($rs["items"]); $i++) {
						// Don't include feed items without a link:
						if (
								( !isset($rs["items"][$i]["magnetURI"]) || empty($rs["items"][$i]["magnetURI"]) ) &&
								( !isset($rs["items"][$i]["enclosure_url"]) || empty($rs["items"][$i]["enclosure_url"]) ) &&
								( !isset($rs["items"][$i]["link"]) || empty($rs["items"][$i]["link"]) )
							){
							array_splice ($rs["items"], $i, 1);
							// Continue to next feed item:
							continue;
						}
		
						// Set the label for the link title (<a href="foo" title="$label">)
						$rs["items"][$i]["label"] = $rs["items"][$i]["title"];
		
						// Check item's pub date:
						if (!isset($rs["items"][$i]["pubDate"]) || empty($rs["items"][$i]["pubDate"]))
							$rs["items"][$i]["pubDate"] = "Unknown publication date";
		
						// Check item's title:
						if (!isset($rs["items"][$i]["title"]) || empty($rs["items"][$i]["title"])) {
							// No title found for this item, create one from the link:
							$link = html_entity_decode($rs["items"][$i]["link"]);
							if (strlen($link) >= 45)
								$link = substr($link, 0, 42)."...";
							$rs["items"][$i]["title"] = "Unknown feed item title: $link";
						} elseif(strlen($rs["items"][$i]["title"]) >= 67){
							// if title string is longer than 70, truncate it:
							// Note this is a quick hack, link titles will also be truncated as well
							// as the feed's display title in the table.
							$rs["items"][$i]["title"] = substr($rs["items"][$i]["title"], 0, 64)."...";
						}
						// decode html entities like &amp; -> & , and then uri_encode them them & -> %26
						// This is needed to get Urls with more than one GET Parameter working
						// (There are 3 common fields used for torrents: enclosure_url, link, magnetURI; enclosure_url is better than link as it is more generally used)
						$rs["items"][$i]["enclosure_url"] = rawurlencode(html_entity_decode($rs["items"][$i]["enclosure_url"]));
						$rs["items"][$i]["link"] = rawurlencode(html_entity_decode($rs["items"][$i]["link"]));
						if ( isset($rs["items"][$i]["magnetURI"]) ) {
							$rs["items"][$i]["magnetURI"] = rawurlencode(html_entity_decode($rs["items"][$i]["magnetURI"]));
						}
					}
					$stat = 1;
					$message = "";
				} else {
					// feed URL is valid and active, but no feed items were found:
					$stat = 2;
					$message = "Feed $url has no items";
				}
			} else {
				// Unable to grab RSS feed, must of timed out
				$stat = 3;
				$message = "Feed $url was not available";
			}
			array_push($rss_list, array(
				'stat' => $stat,
				'rid' => $rid,
				'title' => (isset($rs["title"]) ? $rs["title"] : ""),
				'url' => $url,
				'feedItems' => $rs['items'],
				'message' => $message
				)
			);
		}
		
		$this->rss_list = $rss_list;
	}
		
	//print_r($rss_list);
	
	// TODO: remove this, just to point out the array structure
	//Array
	//(
	//    [0] => Array
	//        (
	//            [stat] => 1
	//            [rid] => 0
	//            [title] => ezRSS - Search Results
	//            [url] => http://www.ezrss.it/search/index.php?show_name=family+guy&date=&quality=&release_group=&mode=rss
	//            [feedItems] => Array
	//                (
	//                    [0] => Array
	//                        (
	//                            [title] => <![CDATA[Family Guy 9x17 [HDTV - REPACK - 2HD]]]>
	//                            [link] => http%3A%2F%2Ftorrent.zoink.it%2FFamily.Guy.S09E17.REPACK.HDTV.XviD-2HD.%5Beztv%5D.torrent
	//                            [description] => <![CDATA[Show Name: Family Guy; Episode Title: N/A; Season: 9; Episode: 17]]>
	//                            [category] => <![CDATA[TV Show / Family Guy]]>
	//                            [comments] => http://eztv.it/forum/discuss/27290/
	//                            [guid] => http://eztv.it/ep/27290/family-guy-s09e17-repack-hdtv-xvid-2hd/
	//                            [pubDate] => Sun, 08 May 2011 21:01:22 -0500
	//                            [label] => <![CDATA[Family Guy 9x17 [HDTV - REPACK - 2HD]]]>
	//                        )
	
	
	
	function show()
	{
		getClientSelection();
		getActionSelection();
		print('<br>');
		
		$this->buildRssItemsArray(); // TODO: not sure were exactly is the best place for this
		
		// TODO: should this javascript be seperated?
		print('
	<script type="text/javascript" src="js/jquery.js"></script>
	<script type="text/javascript">
	function addRssTransfer(url) 
	{
	    // get other values
	    var client = $("#client").val();
	    var subaction = $("#subaction").val();
	    if ( $("#publictorrent").is(":checked") ) {
		var publictorrent = "on";
	    } else {
		var publictorrent = "off";
	    }
	
	    // validate and process form here
	    // TODO: all plugins jquery have to be edited/implemented when an option is added, which is error prone and might be fixed in a cleaner way
	    var dataString = \'url=\' + url + \'&client=\' + client + \'&action=add\' + \'&subaction=\' + subaction + \'&publictorrent=\' + publictorrent;
	
	    $.ajax({
	      type: "POST",
	      url: "dispatcher.php",
	      data: dataString,
	      success: function() {
		showstatusmessage("Transfer added");
		refreshajaxdata();
	      }
	    });
	}
	
	</script>
	'); // get this in a seperate javascript file
		foreach($this->rss_list as $rss_source)
		{
			print("<img src=\"images/rss.png\">RSS Title: " . $rss_source['title'] . "<br>\n");
		
			if(isset($rss_source['feedItems'])) {
				print('<table>');
				foreach($rss_source['feedItems'] as $feedItem)
				{
					print("<tr>");

					$rssitemline = "";
					if ( isset($feedItem['enclosure_url']) && $feedItem['enclosure_url'] !== '' ) {
						$rssitemline .= "<img src=\"images/add.png\" onclick=\"javascript:addRssTransfer('" . $feedItem['enclosure_url'] . "');\">";
					} elseif ( isset($feedItem['link']) && $feedItem['link'] !== '' ) {
						$rssitemline .= "<img src=\"images/add.png\" onclick=\"javascript:addRssTransfer('" . $feedItem['link'] . "');\">";
					}
					if ( isset($feedItem['magnetURI']) && $feedItem['magnetURI'] !== '' ) {
						$rssitemline .= "<img src=\"images/magnet_arrow.png\" onclick=\"javascript:addRssTransfer('" . $feedItem['magnetURI'] . "');\">";
					}
					print("<td>$rssitemline</td>");

					print("<td>" . $feedItem['title'] . "</td>");

					print("</tr>");
				}
				print('</table>');
			}
			if ($rss_source['message'] != '')
				print($rss_source['message'].'<br>');
		}
	
	}

	function getConfiguration()
	{
		print("<form method=post action=configure.php>
  <input type=hidden name=plugin value=rss-transfers>
  <input type=hidden name=action value=set>
  <input type=hidden name=subaction value=add>
  <input type=text name=url>
  <input type=submit text=Add>
</form>");

		require_once('inc/classes/singleton/db.php');
		$db = DB::get_db()->get_handle();
		
		$link_array = array();
		$sql = "SELECT rid, url FROM tf_rss ORDER BY rid";
		$link_array = $db->GetAssoc($sql);
		
		if ($db->ErrorNo() != 0) dbError($sql);

		foreach ( $link_array as $id => $url ) {
			print("<a href=\"configure.php?action=set&subaction=delete&plugin=rss-transfers&rid=$id\"><img src=images/delete.png></a>$url<br>");
		}
	}
	
	function setConfiguration($configArray)
	{
		require_once('inc/classes/singleton/db.php');
		$db = DB::get_db()->get_handle();
		
		if ( $_REQUEST['subaction'] == "delete" ) {
			$sql = "DELETE FROM tf_rss WHERE rid=" . $_REQUEST['rid'];
			$result = $db->Execute($sql);
		
			if ($db->ErrorNo() != 0) dbError($sql);
		}
		
		if ( $_REQUEST['subaction'] == "add" ) {
			print("Nieuwe rss");
			$sql = "INSERT INTO tf_rss (url) VALUES ('" . $_REQUEST['url'] . "')";
			$result = $db->Execute($sql);
		
			print($sql);
			if ($db->ErrorNo() != 0) dbError($sql);
		}
	}


}

?>
