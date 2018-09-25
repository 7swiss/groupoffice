<?php
namespace go\modules\community\music\controller;

use go\core\jmap\EntityController;
use go\modules\community\music\model;

/**
 * The controller for the Artist entity
 *
 * @copyright (c) 2018, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */ 
class Artist extends EntityController {
	
	/**
	 * The class name of the entity this controller is for.
	 * 
	 * @return string
	 */
	protected function entityClass() {
		return model\Artist::class;
	}	
	
}

