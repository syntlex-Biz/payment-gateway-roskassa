<?php
/********************************************************************
Product  : RosKassa
Date  : 1 February 2021
Copyright : © 2021 Syntlex Biz.
Contact  : https://syntlex.biz
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*********************************************************************/
defined('_JEXEC') or die('Restricted Access');

jimport('joomla.form.formfield');
class JFormFieldLine extends JFormField {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'line';

	protected function getInput() {

	 
		$url = "https://roskassa.net/";
		$logo = '<img src="http://roskassa.net/assets/images/logos/logo-text-blue.svg" />';
		$html = '<center><a target="_blank" href="' . $url . '"  >' . $logo . '</a></center>';
		 
		return $html;
	}

}