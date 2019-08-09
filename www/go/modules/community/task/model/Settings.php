<?php
namespace go\modules\community\task\model;
						
use go\core\orm\Property;
						
/**
 * Settings model
 *
 * @copyright (c) 2019, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */

class Settings extends Property {
	
	/**
	 * 
	 * @var int
	 */							
	public $createdBy;

	/**
	 * 
	 * @var int
	 */							
	public $reminderDays = 0;

	/**
	 * 
	 * @var string
	 */							
	public $reminderTime = '0';

	/**
	 * 
	 * @var bool
	 */							
	public $remind = false;

	/**
	 * 
	 * @var int
	 */							
	public $defaultTasklistId = 0;

	protected static function defineMapping() {
		return parent::defineMapping()
						->addTable("task_settings", "settings");
	}

}