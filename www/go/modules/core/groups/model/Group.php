<?php

namespace go\modules\core\groups\model;

use go\core\acl\model\AclOwnerEntity;
use go\core\db\Criteria;
use go\core\orm\Query;
use go\core\validate\ErrorCode;
use go\modules\core\users\model\UserGroup;

/**
 * Group model
 */
class Group extends AclOwnerEntity {

	const ID_ADMINS = 1;
	const ID_EVERYONE = 2;
	const ID_INTERNAL = 3;

	/**
	 *
	 * @var int
	 */
	public $id;
	
	/**
	 *
	 * @var string
	 */
	public $name;

	/**
	 * When this is set this group is the personal group for this user. And only
	 * that user will be member of this group. It's used for granting permissions
	 * to single users but keeping the database simple.
	 * 
	 * @var int
	 */
	public $isUserGroupFor;
	
	/**
	 * Created by user ID 
	 * 
	 * @var int
	 */
	public $createdBy;
	
	/**
	 * The users in this group
	 * 
	 * @var UserGroup[]
	 */
	public $users;
	
	protected function aclEntityClass() {
		
	}
	
	protected static function defineMapping() {
		return parent::defineMapping()
						->addTable('core_group', 'g')
						->addRelation('users', UserGroup::class, ['id' => 'groupId']);
	}
	
	protected static function defineFilters() {
		return parent::defineFilters()
						->add('hideUsers', function(Query $query, $value, $filter) {
							if($value) {
								$query->andWhere(['isUserGroupFor' => null]);	
							}
						})
						->add('excludeEveryone', function(Query $query, $value, $filter) {
							if($value) {
								$query->andWhere('id', '!=', Group::ID_EVERYONE);
							}
						})
						->add('excludeAdmins', function(Query $query, $value, $filter) {
							if($value) {
								$query->andWhere('id', '!=', Group::ID_ADMINS);
							}
						});
						
	}
	
	protected static function searchColumns() {
		return ['name'];
	}
	
	protected function internalSave() {
		
		if(!parent::internalSave()) {
			return false;
		}
		
		if(!$this->isNew()) {
			return true;
		}
		
		return $this->setDefaultPermissions();		
	}
	
	private function setDefaultPermissions() {
		$acl = $this->findAcl();
		//Share group with itself. So members of this group can share with eachother.
		if($this->id !== Group::ID_ADMINS) {
			$acl->groups[] = (new \go\core\acl\model\AclGroup)->setValues(['groupId' => $this->id]);		
		}
		
		foreach(\go\modules\core\groups\model\Settings::get()->getDefaultGroups() as $groupId) {		
			//Share group with everyone. So that everyone can share with all groups. TODO this should be configurable.		
			if($groupId !== Group::ID_ADMINS) {
				$acl->groups[] = (new \go\core\acl\model\AclGroup)->setValues(['groupId' => $groupId]);
			}
		}
		
		return $acl->internalSave();
	}
	
	protected function internalDelete() {
		
		if(isset($this->isUserGroupFor)) {
			$this->setValidationError('isUserGroupFor', ErrorCode::FORBIDDEN, "You can't delete a user's personal group");
			return false;
		}
		
		return parent::internalDelete();
	}

}
