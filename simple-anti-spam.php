<?php
/*
Plugin Name: Simple Anti-Spam
Plugin URI: https://wordpress.org/plugins/simple-antispam/
Description: スパムコメントを拒否するプラグイン
Version: 1.4
Author: oxynotes
Author URI: http://oxynotes.com
License: GPL2

// お決まりのGPL2の文言（省略や翻訳不可）
Copyright 2015 oxy (email : oxy@oxynotes.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/




// インストールパスのディレクトリが定義されているか調べる（プラグインのテンプレ）
if ( !defined('ABSPATH') ) { exit(); }




// アクティベート、ディアクティベート処理
register_activation_hook( __FILE__, array( new Simple_AntiSpam_Activate, 'activate' ) );
register_deactivation_hook( __FILE__, array( new Simple_AntiSpam_Activate, 'deactivate' ) );



/**
 * アクティベート、ディアクティベート専用のクラス
 */
class Simple_AntiSpam_Activate {

	public function activate() {
		$this->add_simple_antispam();
	}

	public function deactivate() {
		$this->remove_simple_antispam();
	}

	// 各種設定と、フィルターのログの初期値を作成
	private function add_simple_antispam() {
		$simple_antispam_setting = get_site_option('simple_antispam_setting');
		if ( ! $simple_antispam_setting ) {
			$setting = array(
				'jp_filter' => 1,
				'js_filter' => 1,
				'ip_filter' => 1
			);
			update_option( 'simple_antispam_setting', $setting );
		}
		$simple_antispam_log = get_site_option('simple_antispam_log');
		if ( ! $simple_antispam_log ) {
			$date = date('Y,m');
			$log = array(
				$date . ',jp_filter' => 0,
				$date . ',js_filter' => 0,
				$date . ',ip_filter' => 0
			);
			update_option( 'simple_antispam_log', $log );
		}
	}

	// 保存した設定とログを削除
	private function remove_simple_antispam() {
		delete_option('simple_antispam_setting');
		delete_option('simple_antispam_log');
	}
}




/**
 * スパム判定と設定画面用クラス
 */
class Simple_AntiSpam {

	/**
	 * 初期設定
	 */
	public function __construct() {

		// 検証用にコメントの時間制限を削除。本番では削除する
		//remove_filter('comment_flood_filter', 'wp_throttle_comment_flood', 10, 3);

		// コメントのフィルター
		// comment_postだとデータベースへ書き込んだ後に処理するためpre_comment_on_postを使う
		add_action( 'pre_comment_on_post', array( $this, 'comment_filter'), 10, 1);

		// 設定が有効な場合、餌用のJavaScriptを追加
		$setting = get_option( 'simple_antispam_setting' );
		if ( ! empty( $setting["js_filter"] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'add_bait_js') );
		}

		// 設定ページの追加
		add_action( 'admin_menu', array( $this, 'add_sas_menu') );

		// 設定ページに追加する項目の定義
		add_action( 'admin_init', array( $this, 'register_mysettings' ) );

		// 設定ページ専用のJavaScriptを追加するために定義
		add_action( 'admin_init', array( $this, 'amcharts_js_init' ) );

	}




	/**
	 * 設定ページの定義
	 */
	public function register_mysettings() {
		register_setting( 'simple-anti-spam-group', 'simple_antispam_setting', array( $this, 'simple_antispam_setting_validation' ) );
	}




	/**
	 * 設定ページの設定とそこで呼び出すJavaScriptとCSSの呼び出し
	 */
	public function add_sas_menu() {

		// JavaScriptを呼び出すために$page_hook_suffixに代入している
		$page_hook_suffix = add_options_page(
			'Simple Anti-Spam', // page_title
			'Simple Anti-Spam', // menu_title
			'administrator', // capability
			'simple-anti-spam', // menu_slug
			array( $this, 'display_plugin_admin_page' ) // function
		);

		// このプラグインの設定画面限定でJavaScriptを呼び出すためのハック
		add_action( 'admin_print_scripts-' . $page_hook_suffix, array( $this, 'amcharts_js' ) );
	}




	/**
	 * 設定画面でグラフ表示用のjsとcssを定義（admin_initで定義している）
	 */
    function amcharts_js_init() {
        wp_register_script( 'amcharts-js', plugins_url( '/js/amcharts.js', __FILE__ ) );
        wp_register_script( 'serial-js', plugins_url( '/js/serial.js', __FILE__ ) );
        wp_register_script( 'export-js', plugins_url( '/js/export.js', __FILE__ ) );
        wp_register_style( 'export-css', plugins_url( '/css/export.css', __FILE__ ) ); // 関数名と違ってcssも追加したけど分けるもの面倒だしこのままでいいね
    }




	/**
	 * amcharts_js_initで定義したjsとcssを追加（hook_suffixで呼び出し用）
	 */
	function amcharts_js() {
		wp_enqueue_script( 'amcharts-js' );
		wp_enqueue_script( 'serial-js' );
		wp_enqueue_script( 'export-js' );
		wp_enqueue_style( 'export-css' );
	}




	/**
	 * コメントフォーム表示時に餌用のJavaScriptを追加
	 * 
	 * 設定を保存するフォームと
	 * AmChartsによるログの表示
	 */
	public function add_bait_js() {
		if( comments_open() ){ // コメントが許可された投稿の場合　is_front_page()等を使えばホームで拒否等できるが、そこまで厳密にするほど重いスクリプトでもないので省略
			// wp_enqueue_scriptの引数：スクリプトのハンドルとして使われる名称、スクリプトのURL、このスクリプトより前に読み込まれる必要があるスクリプト、、スクリプトのバージョン番号、スクリプト追加場所（0で<head>・1で</body>直後）
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'bait-js', plugins_url( '/js/bait.js', __FILE__ ) );
			//wp_die( __('<strong>ERROR</strong>: デバック用') );
		}
	}




	/**
	 * コメントフィルター
	 * 分離してもよいがめんどう
	 * 
	 * @param	int		コメントのID（pre_comment_on_postで呼び出されWordPressから渡される）
	 */
	public function comment_filter( $comment_post_ID ) {

		$setting = get_option( 'simple_antispam_setting' );

		// 日本語を含まない投稿の禁止
		if ( ! empty( $setting["jp_filter"] ) && isset( $_POST['comment'] ) ) {

			$title = get_the_title();
			$post_comment = str_replace( "$title", "", $_POST['comment'] ); // タイトルから日本語を拝借しただけの投稿用

			if ( ! preg_match( "/[ぁ-んァ-ヶー一-龠]+/u", $post_comment ) ) {

				$log = get_option( 'simple_antispam_log' );
				$date = date('Y,m');

				if( isset( $log[$date . ",jp_filter"] ) ) {

					$count = $log[$date . ",jp_filter"];
					$count++;

					foreach ( $log as $key => $val ){
						if( $key == $date . ",jp_filter" ) {
							$log[$date . ",jp_filter"] = $count;
						}
					}

				} else {
					$log = array_merge( $log,array( $date . ",jp_filter" => 1 ) );
				}

				update_option( 'simple_antispam_log', $log );

				wp_die( __('<strong>ERROR</strong>: 日本語を含まないコメントは禁止しています。') );
			}
		}

		/**
		 * JSが無効な環境での投稿
		 * ページ表示後5秒以内の投稿
		 * 不可視項目へ適当な文字列を追加
		 * といったbot対策
		 */
		if ( ! empty( $setting["js_filter"] ) &&
			isset( $_POST['simple_as'] ) &&
			$_POST['simple_as'] != 'bait' ||
			$_POST['simple_as2'] != ''
		) {

			$log = get_option( 'simple_antispam_log' );
			$date = date('Y,m');

			if( isset( $log[$date . ",js_filter"] ) ) { // 対応するカウントがあるか調べる

				$count = $log[$date . ",js_filter"];
				$count++;

				foreach ( $log as $key => $val ){
					if( $key == $date . ",js_filter" ) {
						$log[$date . ",js_filter"] = $count;
					}
				}

			} else { // カウントが無い場合は新規追加
				$log = array_merge( $log,array( $date . ",js_filter" => 1 ) );
			}

			update_option( 'simple_antispam_log', $log );

			wp_die( __('<strong>ERROR</strong>: JavaScriptを有効にしてください。') );
		}

		// スパムに登録されいてるIPでの投稿を拒否（一応データベースへアクセスするのでフィルターの最後に追加）
		if ( ! empty( $setting["ip_filter"] ) ) {

			global $wpdb;
			$results = $wpdb->get_results("SELECT DISTINCT comment_author_IP FROM $wpdb->comments WHERE comment_approved = 'spam' ORDER BY comment_author_IP ASC ");

			foreach ( $results as $result ) {
				$ip_list[] .= $result->comment_author_IP;
			}

			if ( in_array($_SERVER['REMOTE_ADDR'], $ip_list) ) {

				$log = get_option( 'simple_antispam_log' );
				$date = date('Y,m');

				if( isset( $log[$date . ",ip_filter"] ) ) {

					$count = $log[$date . ",ip_filter"];
					$count++;

					foreach ( $log as $key => $val ){
						if( $key == $date . ",ip_filter" ) {
							$log[$date . ",ip_filter"] = $count;
						}
					}

				} else {
					$log = array_merge( $log,array( $date . ",ip_filter" => 1 ) );
				}

				update_option( 'simple_antispam_log', $log );

			    wp_die( __('<strong>ERROR</strong>: IPが規制されています。') ); // これはwp_redirectでもいいかも
			}
		}
	}




	/**
	 * 日時とfilter名を入れるとカウントを返す
	 * 
	 * @param	int		日付（月/年）
	 * @param	str		フィルター名（jp_filter, js_filter, ip_filter）
	 * @param	arg		ログの配列
	 *
	 * @return	int		日付とフィルタ名に対応するログのカウント
	 */
	function get_simple_antispam_log( $date, $filter, $log ) {
		if( isset( $log[str_replace("/", ",", $date) . "," . $filter] ) ) {
			return $log[str_replace("/", ",", $date) . "," . $filter];
		} else {
			return 0;
		}
	}




	/**
	 * 管理画面の設定に追加されるプラグインの設定
	 * 
	 * 設定を保存するフォームと
	 * AmChartsによるログの表示
	 */
	public function display_plugin_admin_page() {

	?>
	 
	<div class="wrap">
	 
	<h2>Simple AntiSpamの設定</h2>
	 
	<form method="post" action="options.php">
	 
	<?php
		settings_fields( 'simple-anti-spam-group' );
		do_settings_sections( 'default' );
		$options = get_option( 'simple_antispam_setting' );
	?>

	<table class="form-table">
	     <tbody>
	     <tr>
	          <th scope="row"><label for="jp_filter">日本語フィルター</label></th>
	          <td>
	               <input type="hidden" name="simple_antispam_setting[jp_filter]" value="0">
	               <label for="jp_filter"><input id="jp_filter" type="checkbox" name="simple_antispam_setting[jp_filter]" size="30" value="1"<?php if( isset($options["jp_filter"]) && $options["jp_filter"] == 1 ) echo ' checked="checked"'; ?>/>日本語を含まないコメントを拒否する</input></label>
	          </td>
	     </tr>
	     </tbody>
	</table>
	<table class="form-table">
	     <tbody>
	     <tr>
	          <th scope="row"><label for="js_filter">JavaScriptフィルター</label></th>
	          <td>
	               <input type="hidden" name="simple_antispam_setting[js_filter]" value="0">
	               <label for="js_filter"><input id="js_filter" type="checkbox" name="simple_antispam_setting[js_filter]" size="30" value="1"<?php if( isset($options["js_filter"]) && $options["js_filter"] == 1 ) echo ' checked="checked"'; ?>/>JavaScriptが無効な場合コメントを拒否する</input></label>
	          </td>
	     </tr>
	     </tbody>
	</table>
	<table class="form-table">
	     <tbody>
	     <tr>
	          <th scope="row"><label for="ip_filter">IPフィルター</label></th>
	          <td>
	               <input type="hidden" name="simple_antispam_setting[ip_filter]" value="0">
	               <label for="ip_filter"><input id="ip_filter" type="checkbox" name="simple_antispam_setting[ip_filter]" size="30" value="1"<?php if( isset($options["ip_filter"]) && $options["ip_filter"] == 1 ) echo ' checked="checked"'; ?>/>スパムを投稿したIPのコメントを拒否する</input></label>
	          </td>
	     </tr>
	     </tbody>
	</table>
	 
	<?php submit_button(); // 送信ボタン ?>
	 
	</form>

	<?php
	// 現在の月から半年分の年と月を取得
	$date = array();
	$date[] = date('Y/m');
	$date[] = date('Y/m', strtotime(date('Y-m-1').' -1 month')); // Y-m-dにすると31日などで上手く動かないので注意
	$date[] = date('Y/m', strtotime(date('Y-m-1').' -2 month'));
	$date[] = date('Y/m', strtotime(date('Y-m-1').' -3 month'));
	$date[] = date('Y/m', strtotime(date('Y-m-1').' -4 month'));
	$date[] = date('Y/m', strtotime(date('Y-m-1').' -5 month')); // 半年前（表示用はここまで）
	$date[] = date('Y/m', strtotime(date('Y-m-1').' -6 month'));

	$log = get_option( 'simple_antispam_log' );
	$log = array_slice($log, 0, 18); // 半年以上経過したのカウントを削除
	update_option( 'simple_antispam_log', $log ); // 古いカウントを削除したオプションを保存し直す
	?>

	<hr />

	<h3>Simple AntiSpamのログ</h3>

	<script>
	AmCharts.makeChart("chartdiv", {
	"type": "serial", // グラフの種類（serialは棒グラフ）
	"legend": { // 凡例　http://docs.amcharts.com/3/javascriptcharts/AmLegend
	    "horizontalGap": 10, // 凡例項目と右/左の境界との間の水平方向のスペース。
	    "maxColumns": 3, // 凡例の横並びの行数。（右や左に設定されている場合は強制的に1行）
	    "position": "bottom", // 凡例の位置 "bottom", "top", "left", "right" and "absolute".
		"markerSize": 16 // 凡例のサイズ。defaultは16px
	},
	"dataProvider":[{ // グラフのデータ http://docs.amcharts.com/3/javascriptcharts/AmChart#dataProvider
		"日時": "<?php echo $date[5]; ?>", // 日付の順序は古い順
		"日本語フィルター": <?php echo $this->get_simple_antispam_log($date[5], "jp_filter", $log ); ?>,
		"JavaScriptフィルター": <?php echo $this->get_simple_antispam_log($date[5], "js_filter", $log ); ?>,
		"IPフィルター": <?php echo $this->get_simple_antispam_log($date[5], "ip_filter", $log ); ?>
	}, {
		"日時": "<?php echo $date[4]; ?>",
		"日本語フィルター": <?php echo $this->get_simple_antispam_log($date[4], "jp_filter", $log ); ?>,
		"JavaScriptフィルター": <?php echo $this->get_simple_antispam_log($date[4], "js_filter", $log ); ?>,
		"IPフィルター": <?php echo $this->get_simple_antispam_log($date[4], "ip_filter", $log ); ?>
	}, {
		"日時": "<?php echo $date[3]; ?>",
		"日本語フィルター": <?php echo $this->get_simple_antispam_log($date[3], "jp_filter", $log ); ?>,
		"JavaScriptフィルター": <?php echo $this->get_simple_antispam_log($date[3], "js_filter", $log ); ?>,
		"IPフィルター": <?php echo $this->get_simple_antispam_log($date[3], "ip_filter", $log ); ?>
	}, {
		"日時": "<?php echo $date[2]; ?>",
		"日本語フィルター": <?php echo $this->get_simple_antispam_log($date[2], "jp_filter", $log ); ?>,
		"JavaScriptフィルター": <?php echo $this->get_simple_antispam_log($date[2], "js_filter", $log ); ?>,
		"IPフィルター": <?php echo $this->get_simple_antispam_log($date[2], "ip_filter", $log ); ?>
	}, {
		"日時": "<?php echo $date[1]; ?>",
		"日本語フィルター": <?php echo $this->get_simple_antispam_log($date[1], "jp_filter", $log ); ?>,
		"JavaScriptフィルター": <?php echo $this->get_simple_antispam_log($date[1], "js_filter", $log ); ?>,
		"IPフィルター": <?php echo $this->get_simple_antispam_log($date[1], "ip_filter", $log ); ?>
	}, {
		"日時": "<?php echo $date[0]; ?>",
		"日本語フィルター": <?php echo $this->get_simple_antispam_log($date[0], "jp_filter", $log ); ?>,
		"JavaScriptフィルター": <?php echo $this->get_simple_antispam_log($date[0], "js_filter", $log ); ?>,
		"IPフィルター": <?php echo $this->get_simple_antispam_log($date[0], "ip_filter", $log ); ?>
	}],
	"categoryField": "日時", // カテゴリの軸の値を指定（ただ、指定しなくても日時が指定されている）
	"valueAxes": [{ // 縦軸に関する設定 http://docs.amcharts.com/3/javascriptcharts/ValueAxis
	    "stackType": "regular", // グラフのタイプ。regularは積み上げタイプ
	    "axisAlpha": 0, // 一番左の軸の不透明度
		"totalText": "[[total]]", // トータルの数値を表示
	    "gridAlpha": 0.05 // 横軸のガイドの不透明度
	}],
	"graphs":[{ // グラフの表示 http://docs.amcharts.com/3/javascriptcharts/AmGraph
	    "balloonText": "<b>[[title]]</b><br><span style='font-size:14px'>[[category]]: <b>[[value]]</b>件</span>", // マウスオーバー時のバルーン表示（サンプルにあるようにグラフの値を[[hoge]]で取得できる）http://docs.amcharts.com/3/javascriptcharts/AmGraph#balloonText
	    "fillAlphas": 1, // グラフの透明度
	    "labelText": "[[value]]", // グラフ内に追加されるテキスト（今回はdataProviderのvalue）
		"lineColor": "#8dbde6", // グラフの色
	    "title": "日本語フィルター", // 右の項目とポップアップ時のタイトル
	    "type": "column", // グラフタイプ（columnは棒グラフ）
		"color": "#000000", // カウントの文字色
	    "valueField": "日本語フィルター" // この項目をグラフにする

	}, {
	    "balloonText": "<b>[[title]]</b><br><span style='font-size:14px'>[[category]]: <b>[[value]]</b>件</span>",
	    "fillAlphas": 1,
	    "labelText": "[[value]]",
	    "lineAlpha": 0.3,
		"lineColor": "#adcfee",
	    "title": "JavaScriptフィルター",
	    "type": "column",
		"color": "#000000",
	    "valueField": "JavaScriptフィルター"
	}, {
	    "balloonText": "<b>[[title]]</b><br><span style='font-size:14px'>[[category]]: <b>[[value]]</b>件</span>",
	    "fillAlphas": 1,
	    "labelText": "[[value]]",
	    "lineAlpha": 0.3,
		"lineColor": "#d6e6f6",
	    "title": "IPフィルター",
	    "type": "column",
		"color": "#000000",
	    "valueField": "IPフィルター"
	}],
	"categoryField": "日時", // この項目が縦軸の下に付くメニューになる
	"categoryAxis": { // カテゴリを利用した縦軸の設定 http://docs.amcharts.com/3/javascriptcharts/CategoryAxis
	    "gridPosition": "start", // グラフの縦軸のバックに引かれる罫線の開始位置。defaultはmiddle
	    "axisAlpha": 0.05, // グラフの下の罫線の不透明度
	    "gridAlpha": 0.05, // グラフの縦軸のバックに引かれる罫線の不透明度
	    "position": "bottom" // 縦軸の項目名の位置（ただ今回のグラフでは上か下しか選べない）　"top", "bottom", "left", "right"
	},
	"export": { // ダウンロードの許可 専用のプラグインを導入する必要あり　http://www.amcharts.com/tutorials/intro-exporting-charts/
		"enabled": true, // ダウンロードを有効に
		"libs": { // ライブラリを指定（これがないとpng等の作成はできない）
			"path": "<?php echo plugins_url( '/js/libs/', __FILE__ ) ?>"
		}
	},
	});

	</script>

	<div id="chartdiv" style="width:100%; height:500px;"></div>

	</div><!-- .wrap -->
	<?php
	}




	/**
	 * 設定のバリデーション（数字にキャストする）
	 */
	public function simple_antispam_setting_validation( $input ) {
		$output = array();
		foreach( $input as $key => $val ){
			$output[$key] = (int) $val;
		}
		return $output;
	}




} // end class




// インスタンスの作成（コンストラクタの実行）
$Simple_AntiSpam = new Simple_AntiSpam();
