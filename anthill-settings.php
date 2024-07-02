<?php

add_action( 'admin_enqueue_scripts', 'anthill_enqueue_admin_settings' );
function anthill_enqueue_admin_settings() {
	$wp_scripts = wp_scripts();

	if (isset($_GET['page']) && $_GET['page'] == 'anthill') {
		wp_enqueue_script('jquery-ui-tabs');
		wp_enqueue_style('jquery-ui','https://ajax.googleapis.com/ajax/libs/jqueryui/'.$wp_scripts->registered['jquery-ui-core']->ver.'/themes/smoothness/jquery-ui.css');
		wp_register_style('anthill_admin_settings_css', plugins_url('/css/anthill-settings.css',__FILE__), array(), '1.0.0' );
		wp_enqueue_style('anthill_admin_settings_css');
	}

	
}



// ADMIN
add_action( 'admin_menu', 'anthill_settings_menu' );
function anthill_settings_menu() {
	add_options_page( 
		'Anthill',
		'Anthill',
		'manage_options',
		'anthill',
		'anthill_settings'
	);
}

function anthill_settings() {
	$updated = $error = false;
	if ( isset( $_POST['anthill_installation'] ) ) {
		// Process URL
		$anthill_installation = esc_attr( $_POST['anthill_installation'] );
		
		// Process username
		$anthill_username = esc_attr( $_POST['anthill_username'] );
		
		// Process key
		$anthill_key = esc_attr( $_POST['anthill_key'] );
		
		// Test ping
		if ($anthill_installation && $anthill_username && $anthill_key ) {
			$anthill_installation_parts = parse_url($anthill_installation);
			if (!isset($anthill_installation_parts['scheme'])) {
				$anthill_installation_parts['scheme'] = 'https';
			}
			if (!isset($anthill_installation_parts['host'])) {
				$anthill_installation_parts['host'] = $anthill_installation_parts['path'];
				$anthill_installation_parts['path'] = '';
			}
			$anthill_installation = $anthill_installation_parts['scheme'].'://'.$anthill_installation_parts['host'].(isset($anthill_installation_parts['path'])? $anthill_installation_parts['path'] : '');

			$anthill_installation_parts = parse_url($anthill_installation);
			if (!isset($anthill_installation_parts['path'])) {
				$anthill_installation .= '/';
			}

			$updated = update_option( 'anthill_installation', $anthill_installation );			
			
			try {
				$ping = Anthill::Ping();
				if ($ping !== 'Pong') {
					$error = 'Unable to connect to Anthill. Please check the details and try again.';
				} else {
					try {
						update_option( 'anthill_username', $anthill_username );
						update_option( 'anthill_key', $anthill_key );
						
						Anthill::GetLocations(); // Test connection to see if credentials work

						$updated = 'Settings updated. Connected to Anthill';
					} catch (Exception $e) {
						$error = 'Unable to connect to Anthill. Please check the details and try again.';
					}
				}
			} catch (Exception $e) {
				$error = 'Unable to connect to Anthill. Please check the details and try again.';
			}
		} else {
			update_option( 'anthill_username', $anthill_username );
			update_option( 'anthill_key', $anthill_key );
			
			$error = 'Please complete all the fields.';
		}
		
	} else {
		$anthill_installation = esc_attr( get_option( 'anthill_installation' ) );
		$anthill_username = esc_attr( get_option( 'anthill_username' ) );
		$anthill_key = esc_attr( get_option( 'anthill_key' ) );
	}
	
	if ( $updated && !$error) {
		echo '<div class="updated fade"><p><strong>' . $updated . '</strong></p></div>';
	}	
	
	if ($error) {
		echo '<div class="error fade"><p><strong>' .$error . '</strong></p></div>';
	}
	

	
	?>
<div class="wrap">
	<h1>Anthill Settings</h1>
	<p>Please enter your settings as supplied by <a href="http://www.anthill.co.uk" target="_blank">Anthill</a>.
	<form method="post" action="<?php print get_admin_url() ?>options-general.php?page=anthill">
		<p>
			<label for="anthill_installation">Installation URL. E.g. https://yourcompany.anthillcrm.com/</label>
			<input title="Installation URL" type="text" name="anthill_installation" id="anthill_installation" 
				   placeholder="Enter your Installation URL here" style="padding: 6px; width:50%; display: block;" 
				   value="<?php echo $anthill_installation; ?>">
		</p>

		<p>
			<label for="anthill_username">API Username</label>
			<input title="Username" type="text" name="anthill_username" id="anthill_username" 
				   placeholder="Enter your username here" style="padding: 6px; width:50%; display: block;" 
				   value="<?php echo $anthill_username; ?>">
		</p>
		
		<p>
			<label for="anthill_key">API Key</label>
			<input title="Key" type="text" name="anthill_key" id="anthill_key" 
				   placeholder="Enter your key here" style="padding: 6px; width:50%; display: block;" 
				   value="<?php echo $anthill_key; ?>">
		</p>
		
		<?php submit_button(); ?>
		

		<?php
		if (!$error && get_option( 'anthill_installation' )) {
			$data = array(
				'Locations' => Anthill::GetLocations(),
				'Customer Types' => Anthill::GetCustomerTypes(),
				'Contact Types' => Anthill::GetCustomerContactTypes(),
				'Attachment Types' => Anthill::GetAttachmentTypes(),
				'Enquiry Types' => Anthill::GetEnquiryTypes(),
				'Issue Types' => Anthill::GetIssueTypes(),
				'Lead Types' => Anthill::GetLeadTypes(),
				'Sale Types' => Anthill::GetSaleTypes(),
			);
			?>
		<h3>Configuration Data</h3>
		
		<div id="anthill_data_tabs">
			<ul>
				<?php
				foreach ($data as $key => $items) {
					?><li><a href="#anthill_data_tabs_<?php print strtolower(str_replace(' ','_',$key)) ?>"><?php print $key ?></a></li><?php
				}
				?>
			</ul>
			<?php
			foreach ($data as $key => $items) {
				?>
				<div id="anthill_data_tabs_<?php print strtolower(str_replace(' ','_',$key)) ?>">
					<table width="100%">
						<tr><th>ID</th><th>Type</th><th>Field</th><th>Field Type</th><th>Options</th><th>Required</th></tr>
						<?php
						foreach ($items as $item) {
							if (is_object($item) && property_exists($item,'id')) {
								
								$fields = property_exists($item, 'Controls')? $item->Controls->detail : array();
								
								if (is_object($fields)) {
									$fields = array($fields);
								}
								
                                /**
                                 * Fields can be null which throws an error, stopping
                                 * the page from loading.
                                 */
                                $nrows = 0;
                                if (is_countable($fields)) {
                                    $nrows = max(1,count($fields));
                                }

								echo '<tr class="field">
									<td rowspan="'.$nrows.'">'.$item->id.'</td>
									<td rowspan="'.$nrows.'">'.$item->name.'</td>';
								foreach ($fields as $f => $field) {
									$options = array();
									if (property_exists($field, 'choice')) {
										$options = is_array($field->choice)? $field->choice : array($field->choice);
									}
									foreach ($options as $i => $option) {
										if (!is_string($option) && !is_numeric($option)) {
											unset($options[$i]);
										}
									}
									echo '<td>'.$field->label.'</td>
										<td>'.$field->type.'</td>
										<td>'.implode(', ',$options).'</td>
										<td>'.$field->required.'</td>';
									echo '</tr><tr>';
								}
								echo '</tr>';
							}
						}
						?>
					</table>
				</div>
				<?php
			}
			?>
		</div>
			<?php
		}
		?>
		
	</form>
</div>

<script>
	jQuery(document).ready(function() {
		jQuery( "#anthill_data_tabs" ).tabs();
	});
</script>

	<?php
}
