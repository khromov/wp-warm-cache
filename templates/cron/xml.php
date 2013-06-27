<?php
	$wcxml = new SimpleXMLElement("<warm_cache_result></warm_cache_result>");
	$wcxml_pages = $wcxml->addChild('pages');
	
	foreach($crawl_data as $page)
		$current_page = $wcxml_pages->addChild('page', $page);
	
	$wcxml->addChild('number_of_pages', sizeof($crawl_data));
	$wcxml->addChild('elapsed_time', $elapsed_time);
	
	//$newsIntro = $wcxml->addChild('content');
	//$newsIntro->addAttribute('type', 'latest');
	
	Header('Content-type: text/xml');
	echo $wcxml->asXML();