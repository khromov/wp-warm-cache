<?php
	$data = new stdClass();
	$data->pages = $crawl_data;
	$data->number_of_pages = sizeof($crawl_data);
	$data->elapsed_time = $elapsed_time;
	
	echo json_encode($data);