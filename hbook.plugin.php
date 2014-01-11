<?php

if ( !defined( 'HABARI_PATH' ) ) {
	die( 'No direct access' );
}

class Hbook extends Plugin
{
	public function configure()
	{
		$ui = new FormUI( 'Hbook' );

		$ui->append('text', 'fb_app_id', 'option:hbook__fb_app_id', _t('Facebook App ID', 'hbook'));
		$ui->append('text', 'fb_app_secret', 'option:hbook__fb_app_secret', _t('Facebook App Secret', 'hbook'));

		$ui->append( 'submit', 'save', _t( 'Save' ) );
		$ui->out();
	}
}

?>