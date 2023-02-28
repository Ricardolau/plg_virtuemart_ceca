<?php
/*
 *      TPVV CECA for VirtueMart 2
 *      @package TPVV CECA for VirtueMart 2
 *      @subpackage Content
 *      @author José António Cidre Bardelás
 *      @copyright Copyright (C) 2011-2014 José António Cidre Bardelás and Joomla Empresa. All rights reserved
 *      @license GNU/GPL v3 or later
 *      
 *      Contact us at info@joomlaempresa.com (http://www.joomlaempresa.es)
 *      
 *      This file is part of TPVV CECA for VirtueMart 2.
 *      
 *          TPVV CECA for VirtueMart 2 is free software: you can redistribute it and/or modify
 *          it under the terms of the GNU General Public License as published by
 *          the Free Software Foundation, either version 3 of the License, or
 *          (at your option) any later version.
 *      
 *          TPVV CECA for VirtueMart 2 is distributed in the hope that it will be useful,
 *          but WITHOUT ANY WARRANTY; without even the implied warranty of
 *          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *          GNU General Public License for more details.
 *      
 *          You should have received a copy of the GNU General Public License
 *          along with TPVV CECA for VirtueMart 2.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('JPATH_BASE') or die('Acesso restrito');

class JFormFieldCECAchave extends JFormField {
	
	protected $type = 'cecachave';

	function getInput() {
		$idPagamento = JFactory::getApplication()->input->get('cid', 0, 'ARRAY');
		//$idPagamento = $idPagamento[0];
		$component = JComponentHelper::getComponent('com_jetpvvcommon', true);
		if(!file_exists(JPATH_ADMINISTRATOR.'/components/com_jetpvvcommon/com_jetpvvcommon.xml') && !file_exists(JPATH_ADMINISTRATOR.'/components/com_jetpvvcommon/j3_com_jetpvvcommon.xml')) {
			$class = ( isset($this->class) ? 'class="'.$this->class.'"' : 'class="text_area"' );
			$size = ( isset($this->size) ? 'size="'.$this->size.'"' : '' );
			return '<input type="text" name="'.$this->name.'" id="'.$this->id.'" value="'.$this->value.'" '.$class.' '.$size.' /><br />'.JText::_('VMPAYMENT_REDSYS_AVISO_CIFRADO');
		}
		elseif(!$component->enabled) {
			$class = ( isset($this->class) ? 'class="'.$this->class.'"' : 'class="text_area"' );
			$size = ( isset($this->size) ? 'size="'.$this->size.'"' : '' );
			return '<input type="text" name="'.$this->name.'" id="'.$this->id.'" value="'.$this->value.'" '.$class.' '.$size.' /><br />'.JText::_('VMPAYMENT_CECA_AVISO_CIFRADO');
		}
		else {

			// Load the modal behavior script.
			JHtml::_('behavior.modal', 'a.modal');

			// Setup variables for display.
			$html = array();
			$jeTPVVToken = version_compare(JVERSION, '3.0.0','ge') ? JSession::getFormToken() : JUtility::getToken();
			$link = 'index.php?option=com_jetpvvcommon&amp;layout=modal&amp;tmpl=component&amp;key='.$this->fieldname.'&amp;cid='.$idPagamento[0].'&amp;'.$jeTPVVToken.'=1';

			// The user select button.
			$html[] = '<div class="button2-left">';
			$html[] = '  <div class="blank">';
			$html[] = '	<a class="modal" title="'.JText::_('VMPAYMENT_CECA_MUDAR_CHAVE_DET').'"  href="'.$link.'" rel="{handler: \'iframe\', size: {x: 800, y: 450}}">'.JText::_('VMPAYMENT_CECA_MUDAR_CHAVE').'</a>';
			$html[] = '  </div>';
			$html[] = '</div>';
			
			return implode("\n", $html);
		}
	}
}
