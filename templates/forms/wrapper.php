<form method="post" action="options.php"> 
	<?php
		settings_fields('warm-cache-group');
		do_settings_sections('warm-cache');	
		submit_button();
	?>
</form>