<h4 style="margin-top: 5px; margin-bottom: 5px;">
	<?=__('CRON url')?>
</h4>
<?=__('The url you should call from your cron job with wget or curl is:')?>
<br/>

<a href="<?=$cron_url?>">
	<?=$cron_url?>
</a>

<h4 style="margin-top: 5px; margin-bottom: 5px;">
	<?=__('Output formats')?>
</h4>

<?=__('You can also fetch the results as machine-readable JSON or XML')?>

<br/>

<a href="<?=$cron_url?>">
	<?=__('HTML')?>
</a>

<a href="<?=$cron_url?>&warm_cache_export_type=json">
	<?=__('JSON')?>
</a>

<a href="<?=$cron_url?>&warm_cache_export_type=xml">
	<?=__('XML')?>
</a>

<br/><br/>

<?=__('Your sitemap was successfully detected at:')?>
<br/>
<a href="<?=$sitemap_url?>">
	<?=$sitemap_url?>
</a>