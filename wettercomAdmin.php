<?php

wettercom_admin_warnings();

function wettercom_admin_warnings() {

	function wettercom_warning() {
		echo "<div id='wettercom-warning' class='updated fade'><p><strong>Das Wetter von wetter.com</strong> ".sprintf(__('Projektname und API-Key fehlen! Tragen Sie diese bitte unter <a href="%1$s">Einstellungen</a> ein.'), "plugins.php?page=wettercom-config")."</p></div>";
	}

	$wettercom_api_key = get_option('wettercom_api_key');
	$wettercom_api_secret = get_option('wettercom_api_secret');

	if (!$wettercom_api_key) {
		add_action('admin_notices', 'wettercom_warning');
	}
	return;

}

add_action('admin_init', 'wettercom_admin_init');

function wettercom_admin_init() {}


add_action('admin_menu', 'wettercom_config_page');

function wettercom_config_page() {
	if (function_exists('add_submenu_page')) {
		add_submenu_page('plugins.php', __('Das Wetter von wetter.com'), __('Das Wetter von wetter.com'), 'manage_options', 'wettercom-config', 'wettercom_config');
	}
}

function wettercom_config() {

	$wettercom_error = '';
	$action = '';
	$search_string = '';

	if (!empty($_POST) && check_admin_referer('wettercom_update_keys')) {
		if (isset($_POST['wettercom_submit'])) {
			$action = 'keyupdate';
			$wettercom_api_user = preg_replace('/[^a-z0-9]/i', '', $_POST['wettercom_api_user']);
			$wettercom_api_key = preg_replace('/[^a-z0-9]/i', '', $_POST['wettercom_api_key']);
			if ((isset($wettercom_api_user) && !empty($wettercom_api_user)) && (isset($wettercom_api_key) && !empty($wettercom_api_key))) {
				update_option('wettercom_api_user', $wettercom_api_user);
				update_option('wettercom_api_key', $wettercom_api_key);
			}
			if (isset($_POST['wettercom_api_citycode']) && !empty($_POST['wettercom_api_citycode'])) {
				$wettercom_api_citycode = preg_replace('/[^a-z0-9]/i', '', $_POST['wettercom_api_citycode']);
				update_option('wettercom_api_citycode', $wettercom_api_citycode);
			}
		} elseif (isset($_POST['search_location'])) {
			$action = 'search';
			$search_string = $_POST['wettercom_api_location'];

			$wettercomService = new wettercomService();
			$search_results = $wettercomService->search($search_string);

			if (isset($search_results) && is_array($search_results)) {
			} else {
				$wettercom_error = $search_results;
			}
		} else {}
	}
	$wettercom_api_citycode = get_option('wettercom_api_citycode');
	$wettercom_api_user = get_option('wettercom_api_user');
	$wettercom_api_key = get_option('wettercom_api_key');



	if ($wettercom_error) {
	?>
	<div class="error"><p><?php echo $wettercom_error; ?></p></div>
	<?php
	}
	?>
	<form action="" method="post">
		<div id="wettercom_admin" class="wettercom_admin">
			<div id="top">Das Wetter von wetter.com</div>
			<div id="api_keys">
				<p style="margin-bottom:15px;">Tragen Sie bitte den Projektnamen und den API-Schlüssel ein. Registrierung unter <a href="http://www.wetter.com/api/" target="_blank" rel="nofollow">http://www.wetter.com/api/</a>.</p>
				<p><label for="wettercom_api_user">Projektname: <input id="wettercom_api_user" name="wettercom_api_user" class="input_box" type="text" size="20" maxlength="32" value="<?php echo htmlspecialchars($wettercom_api_user); ?>"/></label></p>
				<p><label for="wettercom_api_key">API Key: <input id="wettercom_api_key" name="wettercom_api_key" class="input_box" type="text" size="20" maxlength="32" value="<?php echo htmlspecialchars($wettercom_api_key); ?>"/></label></p>
				<?php if ((isset($wettercom_api_user) && !empty($wettercom_api_user)) && (isset($wettercom_api_key) && !empty($wettercom_api_key))) { ?>
					<p style="margin:10px 0px;">Bestimmen Sie den Standort für den die Wetterinformationen angezeigt werden sollen, indem Sie einen Ort eintragen und auf Suchen klicken. Wählen Sie anschließend den Ort aus der unteren Box aus.</p>
					<p><label for="wettercom_api_location">Neuer Standort: <input id="wettercom_api_location" name="wettercom_api_location" class="input_box" type="text" size="20" maxlength="32" value="<?php echo esc_attr($search_string); ?>"/></label><input type="submit" id="search_location" name="search_location" value="Suchen"></p>
					<?php if (($action=='search') && (isset($search_results) && is_array($search_results))) { ?>
						<?php if (count($search_results)>0) { ?>
							<p><label for="wettercom_api_citycode">Auswahl: <select id="wettercom_api_citycode" name="wettercom_api_citycode" class="input_box" cols="20" rows="1" style="width:400px;font-size:1.1em;">
								<?php foreach ($search_results as $key => $value) { ?>
								<option value="<?php echo urlencode($value['city_code']); ?>"><?php
									if (!empty($value['plz'])) { echo $value['plz'].' '; }
									echo htmlspecialchars($value['name']);
									if (!empty($value['adm_2_name'])) { echo ', '.$value['adm_2_name']; }
									if (!empty($value['adm_1_name'])) { echo ', '.$value['adm_1_name']; }
								?></option>
								<?php } ?>
							</select></label></p>

						<?php } else { ?>
							<p style="text-align:center;color:red;">Die Suche lieferte keine passenden Ergebnisse!</p>
						<?php } ?>
					<?php } else if (isset($wettercom_api_citycode) && !empty($wettercom_api_citycode)) { ?>
						<p>Standortcode: <?php echo htmlspecialchars($wettercom_api_citycode); ?></p>
					<?php } ?>
				<?php } ?>
				<?php wp_nonce_field('wettercom_update_keys'); ?>

				<?php if ((isset($wettercom_api_user) && !empty($wettercom_api_user)) && (isset($wettercom_api_key) && !empty($wettercom_api_key))) { ?>
				<p style="margin-top:15px;height:25px;"><input type="submit" id="wettercom_submit" name="wettercom_submit" value="Aktualisieren &raquo;"/></p>
				<?php } else { ?>
				<p style="margin:10px 0px;color:darkred;"><input type="checkbox" id="wettercom_accept" name="wettercom_accept" value="yes" onclick="shButton();"> Sie stimmen zu, dass dieses Plugin das Logo von wetter.com und Links auf wetter.com beinhaltet.</p>
				<script>
				function shButton() {
					if (document.getElementById('wettercom_accept').checked) {
						document.getElementById('wettercom_submit').style.display = '';
					} else {
						document.getElementById('wettercom_submit').style.display = 'none';
					}
				}
				</script>
				<p style="margin-top:15px;height:25px;"><input type="submit" id="wettercom_submit" name="wettercom_submit" value="Aktualisieren &raquo;" style="display:none;"/></p>
				<?php } ?>

			</div>
			<div id="logo"><a href="http://www.wetter.com/" target="_blank" rel="nofollow"><img src="<?php echo WETTERCOM_PLUGIN_URL; ?>images/logo.png"/></a></div>
		</div>
	</form>
	<?php
}

?>
