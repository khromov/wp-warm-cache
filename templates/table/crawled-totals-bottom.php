<h4 style="margin-top: 5px; margin-bottom: 5px;">
	Stats
</h4>
Crawled a total of <?=$v['stats_pages']?> pages in <?=round($v['stats_times'], 2)?>s.
<br/>
<?php if($v['stats_pages']) : ?>
	Average page to load a page in seconds: <?=round($v['stats_times']/$v['stats_pages'], 2)?>s
<?php endif; ?>