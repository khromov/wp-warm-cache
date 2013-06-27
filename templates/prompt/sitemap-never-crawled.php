<?=__('Ok, we have detected your sitemap url but it has not been visited by the plugin\'s crawler.')?>
<br/>
<?=__('This plugin will not work standalone.')?>
<br/>
<strong>
	<?=__('You need to set up a wget or curl cron job from your webhost every hour.')?>
</strong>
<br/>

<?=__('The url you should call from your cron job with wget or curl is:')?>
<br/>
<a href="<?=$cron_url?>">
	<?=$cron_url?>
</a>