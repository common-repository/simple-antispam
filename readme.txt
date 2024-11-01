=== Simple AntiSpam ===
Contributors: oxynotes
Donate link: https://wordpress.org/plugins/simple-antispam/
Tags: spam, comment, japanese
Requires at least: 4.3.1
Tested up to: 4.3.1
Stable tag: 1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

有効にするだけでスパムコメントを拒否します

== Description ==

Simple AntiSpamはスパムコメントを拒否するプラグインです。
目指したのは有効しただけで動作する簡単なスパム対策です。

以下のフィルターでスパムによるコメントを拒否します。

= 日本語フィルター =

日本語を含まない投稿を拒否します。

= JavaScriptフィルター =

JavaScriptが無効な場合コメントを拒否します。
また、入力項目に適当な値を入れるタイプのスパムや、ページを表示して5秒以内のコメントも拒否します。

= IPフィルター =

コメントがスパムに登録されているIPからの投稿を拒否します。
Akismetとの連動が前提です。Akismetの設定でAlways put spam in the Spam folder for review.にチェックを入れておくとスパムが15日間保存されるます。
そのためスパムを投稿したIPからの投稿は15日間無効になります。

それぞれのフィルターは設定画面で有効・無効を変更できます。

= Simple AntiSpamのログ =

各フィルターの動作ログはamChartsを利用したグラフで確認することができます。

詳しい使い方や解説は[作者の解説ページ](http://oxynotes.com/?p=9685)をご覧ください。

== Installation ==

1. プラグインの新規追加ボタンをクリックして、検索窓に「Simple AntiSpam」と入力して「今すぐインストール」をクリックします。
1. もしくはこのページのzipファイルをダウンロードして解凍したフォルダを`/wp-content/plugins/`ディレクトリに保存します。
1. 設定画面のプラグインで **Simple AntiSpam** を有効にしてください。

== Frequently asked questions ==

-

== Screenshots ==

1. Option page.

== Changelog ==
1.4
カタカナや漢字のみだと書き込めない不具合を修正。

1.3
amChartsのlibを内包する形に変更。

1.2
バグ修正とフィルター修正。

1.0
初めのバージョン。


== Upgrade notice ==

-