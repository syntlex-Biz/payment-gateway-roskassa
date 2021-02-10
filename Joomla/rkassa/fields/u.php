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
class JFormFieldU extends JFormField {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'u';

	protected function getInput() {

	 
		$url = JURI::root()."index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&method=rkassa&tmpl=component";
	 	$html = '<center> ' . $url . ' </center>';
		 
		return $html;
	}

}