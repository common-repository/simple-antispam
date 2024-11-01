/*
 * コメントフォームに不可視項目を追加する
 * 
 * JavaScriptを実行しない環境で投稿を拒否
 * 
 * JavaScriptを実行するスパムの場合でもbait以外の値が入力された時は拒否
 * または5秒位内にコメントが投稿された場合も拒否
 * 判定はcomment_filter()で行う
 * 
 * botが学習した場合サイトごとに一意の値になるようにsaltやtokenを発行してajaxで取得してもいいが、
 * そこまでする必要もないと思われる
*/

jQuery(function(){
	setTimeout(function(){
		jQuery('form#commentform').append('<input type="hidden" name="simple_as" id="simple_as" value="bait" />');
		//jQuery('form#commentform').append('<input style="display:none" name="simple_as" id="simple_as" value="bait" />'); // どちらが良いかは計測して確認すべし
		jQuery('form#commentform').append('<input type="input" style="display:none" name="simple_as2" id="simple_as2" value="" />');
	}, 5000);
});