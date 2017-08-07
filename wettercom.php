<?php
/*
Plugin Name: Das Wetter von wetter.com
Plugin URI: http://www.wetter.com/
Description: Das Wetter für deinen Standort
Version: 1.1
Author: wetter.com AG
Author URI: http://www.wetter.com/
*/

define('WETTERCOM_PLUGIN_VERSION', '1.1');
define('WETTERCOM_PLUGIN_URL', plugin_dir_url( __FILE__ ));
define('WETTERCOM_SEARCH_URL', 'http://api.wetter.com/location/index/');
define('WETTERCOM_FORECAST_URL', 'http://api.wetter.com/forecast/weather/city/');
define('WETTERCOM_UINTERVAL', 3600);

function wettercom_widget_init() {
	register_widget('wettercomWidget');

	wp_register_style('widget.css', WETTERCOM_PLUGIN_URL.'widget.css');
	wp_enqueue_style('widget.css');
}
add_action('widgets_init', 'wettercom_widget_init');

if (is_admin()) require_once dirname( __FILE__ ).'/wettercomAdmin.php';

class wettercomService {

	var $wettercom_api_user;
	var $wettercom_api_key;
	var $wettercom_api_citycode;
	var $wettercom_data_forecast;
	var $wettercom_data_updatetime;
	var $wettercom_search_string;

	function __construct() {
	}

	function search($loc) {
		global $wp_version;

		if (strlen($loc)<2 || strlen($loc)>64) {
			return 'Suchbegriff zu kurz oder zu lang.';
		}

		$wettercom_api_user = get_option('wettercom_api_user');
		$wettercom_api_key = get_option('wettercom_api_key');
		$wettercom_search_string = $loc;

		$cs = md5($wettercom_api_user.$wettercom_api_key.$wettercom_search_string);
		$url = WETTERCOM_SEARCH_URL.'search/'.urlencode($wettercom_search_string).'/project/'.urlencode($wettercom_api_user).'/cs/'.$cs.'/output/json';
		$options = array('user-agent' => 'WordPress/'.$wp_version);

		$data = wp_remote_get($url, $options);
		if (is_wp_error($data)) {
			return strval($data->get_error_message());
		} else {
			if (function_exists('json_decode')) {
				if ($response = json_decode($data['body'], true)) {
					if (isset($response['error']) && is_array($response['error'])) {
						return strval('<b>'.$response['error']['title'].'</b> '.$response['error']['message']);
					} else if (isset($response['search']['result']) && is_array($response['search']['result'])) {
						return $response['search']['result'];
					} else {
						return 'Die Suche konnte nicht durchgeführt werden. Benutzen Sie die Suche in wenigen Minuten erneut.';
					}
				} else {
					return 'Die empfangenen Daten konnten nicht verarbeitet werden.';
				}
			} else {
				return 'Das Plugin benötigt die JSON PHP Extension.';
			}
		}

	}

	function load() {
		global $wp_version;

		$wettercom_api_citycode = get_option('wettercom_api_citycode');
		if (empty($wettercom_api_citycode)) {
			return 'Ungültiger Standort-Code.';
		}
		$wettercom_api_user = get_option('wettercom_api_user');
		$wettercom_api_key = get_option('wettercom_api_key');

		$cs = md5($wettercom_api_user.$wettercom_api_key.$wettercom_api_citycode);
		$url = WETTERCOM_FORECAST_URL.urlencode($wettercom_api_citycode).'/project/'.$wettercom_api_user.'/cs/'.$cs.'/output/json';
		$options = array('user-agent' => 'WordPress/'.$wp_version);

		$data = wp_remote_get($url, $options);
		if (is_wp_error($data)) {
			return strval($data->get_error_message());
		} else {
			if (function_exists('json_decode')) {
				if ($response = json_decode($data['body'], true)) {
					$today = date("Y-m-d");
					if (isset($response['error']) && is_array($response['error'])) {
						return strval('<b>'.$response['error']['title'].'</b> '.$response['error']['message']);
					} else if (isset($response['city']['forecast'][$today]) && is_array($response['city']['forecast'][$today])) {

						$forecast = $response['city']['forecast'][$today];
						if (isset($response['city']['city_code']) && !empty($response['city']['city_code'])) { $forecast['city_code'] = $response['city']['city_code']; }
						if (isset($response['city']['name']) && !empty($response['city']['name'])) { $forecast['name'] = $response['city']['name']; }
						if (isset($response['city']['post_code']) && !empty($response['city']['post_code'])) { $forecast['post_code'] = $response['city']['post_code']; }
						if (isset($response['city']['url']) && !empty($response['city']['url'])) { $forecast['url'] = $response['city']['url']; }
						update_option('wettercom_data_forecast', $forecast);
						$now = time();
						update_option('wettercom_data_updatetime', $now);
						return $forecast;

					} else {
						return 'Die Wetterdaten konnten nicht abgerufen werden.';
					}
				} else {
					return 'Die empfangenen Daten konnten nicht verarbeitet werden.';
				}
			} else {
				return 'Das Plugin benötigt die JSON PHP Extension.';
			}
		}
	}

	function get() {
		$wettercom_data_forecast = get_option('wettercom_data_forecast');
		if (isset($wettercom_data_forecast) && is_array($wettercom_data_forecast)) {
			return $wettercom_data_forecast;
		}
		return false;
	}

}

class wettercomWidget extends WP_Widget {

    function wettercomWidget() {
		$this->WP_Widget('wettercomWidget', 'Das Wetter von wetter.com');
    }

    function widget($args, $instance) {
        extract($args, EXTR_SKIP);

		$forecast = get_option('wettercom_data_forecast');

		$wettercomService = new wettercomService();
		if (isset($forecast) && is_array($forecast)) {
			$update = get_option('wettercom_data_updatetime');
			$now = time();
			$wettercom_api_citycode = get_option('wettercom_api_citycode');
			if ($update<($now-WETTERCOM_UINTERVAL) || $forecast['city_code']!=$wettercom_api_citycode) {
				$forecast = $wettercomService->load();
			} else {
				if (!$forecast = $wettercomService->get()) {
					$forecast = $wettercomService->load();
				}
			}
		} else {
			$forecast = $wettercomService->load();
		}

		if (isset($forecast) && is_array($forecast)) {
			$windstr = array('N'=>'Nordwind','NE'=>'Nordostwind','E'=>'Ostwind','SE'=>'Südostwind','O'=>'Ostwind','SO'=>'Südostwind','S'=>'Südwind','SW'=>'Südwestwind','W'=>'Westwind','NW'=>'Nordwestwind');
			if (isset($forecast['d'])) { $d = $forecast['d']; } else { $d = ''; }
			if (isset($forecast['du'])) { $du = $forecast['du']; } else { $du = ''; }
			if (isset($forecast['dhl'])) { $dhl = $forecast['dhl']; } else { $dhl = ''; }
			if (isset($forecast['dhu'])) { $dhu = $forecast['dhu']; } else { $dhu = ''; }
			if (isset($forecast['p'])) { $p = $forecast['p']; } else { $p = ''; }
			if (isset($forecast['pc'])) {
				$pc = $forecast['pc'].'%';
				if (intval($forecast['pc'])<50) {
					$pc_icon = 'niederschlag';
				} else {
					$pc_icon = 'niederschlag_wet';
				}
			} else {
				$pc = '';
				$pc_icon = '';
			}
			if (isset($forecast['tn'])) { $tn = $forecast['tn']; } else { $tn = ''; }
			if (isset($forecast['tx'])) { $tx = $forecast['tx']; } else { $tx = ''; }
			if ($tn && $tx) {
				$t = $tn.' / '.$tx.'°C';
			} else { $t = ''; }
			if (isset($forecast['w'])) { $w = $forecast['w']; } else { $w = ''; }
			if (isset($forecast['w_txt'])) { $w_txt = $forecast['w_txt']; } else { $w_txt = ''; }
			if (isset($forecast['wd'])) { $wd = $forecast['wd']; } else { $wd = ''; }
			if (isset($forecast['wd_txt'])) { $wd_txt = $forecast['wd_txt']; } else { $wd_txt = ''; }
			if (isset($forecast['ws'])) { $ws = $forecast['ws'].' km/h'; } else { $ws = ''; }
			if (isset($forecast['city_code'])) { $city_code = $forecast['city_code']; } else { $city_code = ''; }
			if (isset($forecast['name'])) { $name = $forecast['name']; } else { $name = ''; }
			if (isset($forecast['post_code'])) { $post_code = $forecast['post_code']; } else { $post_code = ''; }
			if (isset($forecast['url'])) { $url = $forecast['url']; } else { $url = ''; }

			?>
			<?php echo $before_widget; ?>
			<?php echo $before_title; ?><a href="http://www.wetter.com/<?php echo $url; ?>" target="_blank" rel="nofollow" title="<?php echo 'Das Wetter für '.htmlspecialchars($name); ?>"><?php echo 'Das Wetter für '.htmlspecialchars($name); ?></a><?php echo $after_title; ?>
			<div id="wettercom_box">

				<div class="spacing_s"></div>

				<?php if ($t || $w_txt) { ?>
				<div id="wx_icon"><div class="wxicon" style="margin:auto;<?php if (isset($w)) { echo 'background-image:url('.WETTERCOM_PLUGIN_URL.'images/icons/d_'.$w.'_b.png);'; } ?>"></div></div>
				<div id="wx_main">
					<?php if ($t) { ?>
					<div class="text_l" style="margin:7px 0px;"><?php echo $t; ?></div>
					<?php } ?>
					<?php if ($w_txt) { ?>
					<div class="text_m" style="margin-bottom:3px;"><?php echo $w_txt; ?></div>
					<?php } ?>
				</div>
				<div class="cleaner"></div>
				<?php } ?>

				<?php if ($wd || $ws) { ?>
				<div class="spacing"></div>
				<div id="wind_icon"><div class="wind" style="margin:auto;<?php if (isset($wd_txt)) { echo 'background-image:url('.WETTERCOM_PLUGIN_URL.'images/icons/'.$wd_txt.'.png);'; } ?>"></div></div>
				<div id="wind_box">
					<?php if ($wd) { ?>
					<div class="text_m"><?php if (!empty($wd_txt)) { echo $windstr[$wd_txt]; } ?></div>
					<div class="text_s" style="margin-bottom:3px;">Windrichtung</div>
					<?php } ?>
					<?php if ($ws) { ?>
					<div class="text_m"><?php echo $ws; ?></div>
					<div class="text_s" style="margin-bottom:3px;">Geschwindigkeit</div>
					<?php } ?>
				</div>
				<div class="cleaner"></div>
				<?php } ?>

				<?php if ($pc) { ?>
				<div class="spacing"></div>
				<div id="ns_icon"><div class="nsicon" style="margin:auto;<?php if (isset($pc_icon)) { echo 'background-image:url('.WETTERCOM_PLUGIN_URL.'images/icons/'.$pc_icon.'.png);'; } ?>"></div></div>
				<div id="ns_box">
					<div class="text_m"><?php echo $pc; ?></div>
					<div class="text_s" style="margin-bottom:3px;">Niederschlag?</div>
				</div>
				<div class="cleaner"></div>
				<?php } ?>

				<?php if ($d) { ?>
				<div id="date" class="text_s">Vorhersage für <?php echo date("d.m.Y", $d); ?></div>
				<?php } ?>

				<div class="spacing_s"></div>
				<div id="more"><a href="http://www.wetter.com/<?php echo $url; ?>" target="_blank" rel="nofollow" title="<?php echo 'Das Wetter für '.htmlspecialchars($name); ?>">» weitere Werte</a></div>
				<div id="logo"><a href="http://www.wetter.com/" target="_blank" rel="nofollow" style="border:0px none;" title="wetter.com - Wetter, Wettervorhersage, Wetterbericht, Reise"><img src="<?php echo WETTERCOM_PLUGIN_URL; ?>images/logo.png" alt="wetter.com Logo"/></a></div>
				<div class="cleaner"></div>

				<div class="spacing_s"></div>

			</div>
			<?php echo $after_widget; ?>
			<?php
		} else {
			?>
			<?php echo $before_widget; ?>
			<?php echo $before_title.'Das Wetter von wetter.com'.$after_title; ?>
			<div id="wettercom_error">
				<div class="msg">Fehler: <?php echo $forecast; ?></div>
			</div>
			<?php echo $after_widget; ?>
			<?php
		}
    }

    function update($new_instance, $old_instance) {
		$instance = $old_instance;
		return $instance;
	}


	function form($instance) {
		?>
		<p>Die Einstellungen können <a href="plugins.php?page=wettercom-config">hier</a> vorgenommen werden.</p>
		<?php
    }



}

?>
