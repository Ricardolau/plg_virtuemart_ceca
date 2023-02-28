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
if(!defined('_JEXEC'))
	die('Acesso a '.basename(__FILE__).' restrito.');

class JFormFieldCECAinstrucoes extends JFormField {
	
	protected $type = 'cecainstrucoes';

	function getInput() {
			$html = array();
			$link = JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component';
			$html = JText::sprintf($this->default, $link);
			return $html;
		}
}
