/*
 * �R�����g�t�H�[���ɕs�����ڂ�ǉ�����
 * 
 * JavaScript�����s���Ȃ����œ��e������
 * 
 * JavaScript�����s����X�p���̏ꍇ�ł�bait�ȊO�̒l�����͂��ꂽ���͋���
 * �܂���5�b�ʓ��ɃR�����g�����e���ꂽ�ꍇ������
 * �����comment_filter()�ōs��
 * 
 * bot���w�K�����ꍇ�T�C�g���ƂɈ�ӂ̒l�ɂȂ�悤��salt��token�𔭍s����ajax�Ŏ擾���Ă��������A
 * �����܂ł���K�v���Ȃ��Ǝv����
*/

jQuery(function(){
	setTimeout(function(){
		jQuery('form#commentform').append('<input type="hidden" name="simple_as" id="simple_as" value="bait" />');
		//jQuery('form#commentform').append('<input style="display:none" name="simple_as" id="simple_as" value="bait" />'); // �ǂ��炪�ǂ����͌v�����Ċm�F���ׂ�
		jQuery('form#commentform').append('<input type="input" style="display:none" name="simple_as2" id="simple_as2" value="" />');
	}, 5000);
});