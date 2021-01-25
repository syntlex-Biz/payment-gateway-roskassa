<?php
defined('_JEXEC') or die('Restricted access');

/*
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

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

	 
		$url = JURI::root()."index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&method=roskassa&tmpl=component";
	 	$html = '<center> ' . $url . ' </center>';
		 
		return $html;
	}

}