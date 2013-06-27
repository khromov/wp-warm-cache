<!DOCTYPE html>
<html>
	<head>
		<meta charset=utf-8 />
		<title><?=__("Varm Cache report")?></title>
	</head>

	<body>
		<?php foreach($crawl_data as $page): ?>
			Busy with: <?=$page?> <br/>
		<?php endforeach; ?>
		<br/>
		<strong>
			Done!
		</strong>
		<br/>
		Crawled <?=sizeof($crawl_data)?> pages in <?=$elapsed_time?> seconds.
	</body>
</html>