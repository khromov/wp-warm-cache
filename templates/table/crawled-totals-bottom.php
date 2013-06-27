<h4 style="margin-top: 5px; margin-bottom: 5px;">
	Stats
</h4>
Crawled a total of <?=$stats_pages?> pages in <?=round($stats_times, 2)?>s.
<br/>
<?php if($stats_pages) : ?>
	Average page to load a page in seconds: <?=round($stats_times/$stats_pages, 2)?>s
<?php endif; ?>