<h3>
	<?=__('Latest crawls log')?>
</h3>
<table class="wp-list-table widefat fixed posts">
	<thead>
		<?=$template->show('table/crawled-header-footer')?>
	</thead>
	
	<?=$table_string?>
	
	<tfoot>
		<?=$template->show('table/crawled-header-footer')?>
	</tfoot>
</table>