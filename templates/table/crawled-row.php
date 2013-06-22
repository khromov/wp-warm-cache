<tr>
	<td valign="top">
		<?=date('l jS F Y h:i:s A', $v['time_start'])?>
	</td>
	<td valign="top" style="text-align: center;">
		<?=round($v['time'], 2)?>s
	</td>
	<td valign="top">
		<?=$v['pages_count']?>
	</td>
	<td valign="top">
		<?=(intval($v['pages_count'])!=0) ? round($v['time'] / $v['pages_count'], 2).'s' : '-'?>
	</td>
	<!--
	<td valign="top">
	<?php
	/*
	if(intval($temp['pages_count']) > 0)
	{
		foreach($temp['pages'] as $p_key => $p_value)
		{
			$table_string .= '<a href="'.$p_value.'" title="'.$p_value.'">';
			$temp_string = str_replace($site_url,'',$p_value);
			if($temp_string == '/')	{ $temp_string = $site_url; } // Site url, show this instead of "/"			
			$table_string .= $temp_string;
			$table_string .= '</a>';
			$string_length += strlen($temp_string);
			if($string_length > 70) {$string_length =0; $table_string .= '<br/>';} // New line
			$table_string .= "\n";
		}
	}
	*/
	?>
	-
	</td>
	-->
</tr>
<?="\n\n"?>