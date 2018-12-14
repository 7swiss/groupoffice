<?php

namespace go\core\orm;

use DateTime;
use Exception;
use GO;
use go\core\App;
use go\core\db\Query;
use go\core\jmap\Entity;
use go\modules\core\modules\model\Module;

/**
 * The EntityType class
 * 
 * This holds information about the entity.
 * 
 * id: The ID in the database used for foreign keys
 * className: The PHP class name used in the PHP API
 * name: The name of the entity for the JMAP client API
 * moduleId: The module ID this entity belongs to
 * 
 * It's also used for routing short routes like "Note/get" instead of "community/notes/Note/get"
 * 
 */
class EntityType {

	private $className;	
	private $id;
	private $name;
	private $moduleId;	
  private $clientName;
	
	/**
	 * The highest mod sequence used for JMAP data sync
	 * 
	 * @var int
	 */
	public $highestModSeq;
	
	private $highestUserModSeq;
	
	private $modSeqIncremented = false;
	
	private $userModSeqIncremented = false;
	
	/**
	 * The name of the entity for the JMAP client API
	 * 
	 * eg. "note"
	 * @return string
	 */
	public function getName() {
		return $this->clientName;
	}
	
	/**
	 * The PHP class name used in the PHP API
	 * 
	 * @return string
	 */
	public function getClassName() {
		return $this->className;
	}
	
	/**
	 * The ID in the database used for foreign keys
	 * 
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}
	
	/**
	 * The module ID this entity belongs to
	 * 
	 * @return in
	 */
	public function getModuleId() {
		return $this->moduleId;
	}	
	
	
	/**
	 * Get the module this type belongs to.
	 * 
	 * @return Module
	 */
	public function getModule() {
		return Module::findById($this->moduleId);
	}

	/**
	 * Find by PHP API class name
	 * 
	 * @param string $className
	 * @return static
	 */
	public static function findByClassName($className) {

		$e = new static;
		$e->className = $className;
		
		$record = (new Query)
						->select('*')
						->from('core_entity')
						->where('clientName', '=', $className::getClientName())
						->single();

		if (!$record) {
			$module = Module::findByClass($className);
		
			if(!$module) {
				throw new Exception("No module found for ". $className);
			}

			$record = [];
			$record['moduleId'] = isset($module) ? $module->id : null;
			$record['name'] = self::classNameToShortName($className);
      $record['clientName'] = $className::getClientName();
			App::get()->getDbConnection()->insert('core_entity', $record)->execute();

			$record['id'] = App::get()->getDbConnection()->getPDO()->lastInsertId();
		} else
		{
			$e->highestModSeq = isset($record['highestModSeq']) ? (int) $record['highestModSeq'] : null;
		}

		$e->id = $record['id'];
		$e->moduleId = $record['moduleId'];
		$e->clientName = $record['clientName'];
		$e->name = $record['name'];
		
		
		return $e;
	}
	
	/**
	 * Creates a short name based on the class name.
	 * 
	 * This is used to generate response name. 
	 * 
	 * eg. class go\modules\community\notes\model\Note becomes just "note"
	 * 
	 * @return string
	 */
	private static function classNameToShortName($cls) {
		return substr($cls, strrpos($cls, '\\') + 1);
	}
	
	/**
	 * Find all registered.
	 * 
	 * @return static[]
	 */
	public static function findAll() {
		$records = (new Query)
						->select('e.*, m.name AS moduleName, m.package AS modulePackage')
						->from('core_entity', 'e')
						->join('core_module', 'm', 'm.id = e.moduleId')
						->where(['m.enabled' => true])
						->all();
		
		$i = [];
		foreach($records as $record) {
			$i[] = static::fromRecord($record);
		}
		
		return $i;
	}

	/**
	 * Find by db id
	 * 
	 * @param int $id
	 * @return static
	 */
	public static function findById($id) {
		$record = (new Query)
						->select('e.*, m.name AS moduleName, m.package AS modulePackage')
						->from('core_entity', 'e')
						->join('core_module', 'm', 'm.id = e.moduleId')
						->where('id', '=', $id)
						->single();
		
		if(!$record) {
			return false;
		}
		
		return static::fromRecord($record);
	}
	
	/**
	 * Find by client API name
	 * 
	 * @param string $name
	 * @return static
	 */
	public static function findByName($name) {
		$record = (new Query)
						->select('e.*, m.name AS moduleName, m.package AS modulePackage')
						->from('core_entity', 'e')
						->join('core_module', 'm', 'm.id = e.moduleId')
						->where('clientName', '=', $name)
						->single();
		
		if(!$record) {
			return false;
		}
		
		return static::fromRecord($record);
	}
	
	/**
	 * Convert array of entity names to ids
	 * 
	 * @param string $names eg ['Contact', 'Note']
	 * @return int[] eg. [1,2]
	 */
	public static function namesToIds($names) {
		return array_map(function($name) {
			$e = static::findByName($name);
			if(!$e) {
				throw new \Exception("Entity '$name'  not found");
			}
			return $e->getId();
		}, $names);	
	}
  

	private static function fromRecord($record) {
		$e = new static;
		$e->id = $record['id'];
		$e->name = $record['name'];
    $e->clientName = $record['clientName'];
		$e->moduleId = $record['moduleId'];
		$e->highestModSeq = (int) $record['highestModSeq'];

		if (isset($record['modulePackage'])) {
			$e->className = 'go\\modules\\' . $record['modulePackage'] . '\\' . $record['moduleName'] . '\\model\\' . ucfirst($e->name);
		} else {			
			$e->className = 'GO\\' . ucfirst($record['moduleName']) . '\\Model\\' . ucfirst($e->name);			
		}
		
		return $e;
	}
	
	/**
	 * Register multiple changes for JMAP
	 * 
	 * This function increments the entity type's modSeq so the JMAP sync API 
	 * can detect this change for clients.
	 * 
	 * It writes the changes into the 'core_change' table.
	 * 	 
	 * @param Query $changedEntities A query object that provides "entityId", "aclId" and "destroyed" in this order!.
	 */
	public function changes(Query $changedEntities) {		
		
		GO()->getDbConnection()->beginTransaction();
		
		$this->highestModSeq = $this->nextModSeq();		
		
		$changedEntities->select('"' . $this->getId() . '", "'. $this->highestModSeq .'", NOW()', true);		
		
		try {
			$stmt = GO()->getDbConnection()->insert('core_change', $changedEntities, ['entityId', 'aclId', 'destroyed', 'entityTypeId', 'modSeq', 'createdAt']);
			$stmt->execute();
		} catch(\Exception $e) {
			GO()->getDbConnection()->rollBack();
			throw $e;
		}
		
		if(!$stmt->rowCount()) {
			//if no changes were written then rollback the modSeq increment.
			GO()->getDbConnection()->rollBack();
		} else
		{
			GO()->getDbConnection()->commit();
		}				
	}
	
	/**
	 * Register a change for JMAP
	 * 
	 * This function increments the entity type's modSeq so the JMAP sync API 
	 * can detect this change for clients.
	 * 
	 * It writes the changes into the 'core_change' table.
	 * 
	 * It also writes user specific changes 'core_user_change' table ({@see \go\core\orm\Mapping::addUserTable()). 
	 * 
	 * @param Entity $entity
	 */
	public function change(Entity $entity) {
		$this->highestModSeq = $this->nextModSeq();

		$record = [
				'modSeq' => $this->highestModSeq,
				'entityTypeId' => $this->id,
				'entityId' => $entity->getId(),
				'aclId' => $entity->findAclId(),
				'destroyed' => $entity->isDeleted(),
				'createdAt' => new DateTime()
						];

		if(!GO()->getDbConnection()->insert('core_change', $record)->execute()) {
			throw new \Exception("Could not save change");
		}
	}
		
	/**
	 * Checks if a saved entity needs changes for the JMAP API with change() and userChange()
	 * 
	 * @param Entity $entity
	 * @throws Exception
	 */
	public function checkChange(Entity $entity) {
		
		if(!$entity->isDeleted()) {
			$modifiedPropnames = array_keys($entity->getModified());		
			$userPropNames = $entity->getUserProperties();

			$entityModified = !empty(array_diff($modifiedPropnames, $userPropNames));
			$userPropsModified = !empty(array_intersect($userPropNames, $modifiedPropnames));
		} else
		{
			$entityModified = true;
			$userPropsModified = false;
		}
		
		
		if($entityModified) {
			$this->change($entity);
		}
		
		if($userPropsModified) {
			$this->userChange($entity);
		}
		
		if($entity->isDeleted()) {
			
			$where = [
					'entityTypeId' => $this->id,
					'entityId' => $entity->getId(),
					'userId' => GO()->getUserId()
							];
			
			$stmt = GO()->getDbConnection()->delete('core_change_user', $where);
			if(!$stmt->execute()) {
				throw new \Exception("Could not delete user change");
			}
		}
	}
	
	private function userChange(Entity $entity) {
		$data = [
				'modSeq' => $this->nextUserModSeq()			
						];

		$where = [
				'entityTypeId' => $this->id,
				'entityId' => $entity->getId(),
				'userId' => GO()->getUserId()
						];

		$stmt = GO()->getDbConnection()->update('core_change_user', $data, $where);
		if(!$stmt->execute()) {
			throw new \Exception("Could not save user change");
		}

		if(!$stmt->rowCount()) {
			$where['modSeq'] = 1;
			if(!GO()->getDbConnection()->insert('core_change_user', $where)->execute()) {
				throw new \Exception("Could not save user change");
			}
		}
	}
	
	/**
	 * Get the modSeq for the user specific properties.
	 * 
	 * @return string
	 */
	public function getHighestUserModSeq() {
		if(!isset($this->highestUserModSeq)) {
		$this->highestUserModSeq = (int) (new Query())
						->selectSingleValue("highestModSeq")
						->from("core_change_user_modseq")
						->where(["entityTypeId" => $this->id, "userId" => GO()->getUserId()])
						->forUpdate()->single();
		}
		return $this->highestUserModSeq;
	}
	
	
	/**
	 * Get the modification sequence
	 * 
	 * @param string $entityClass
	 * @return int
	 */
	public function nextModSeq() {
		
		if($this->modSeqIncremented) {
			return $this->highestModSeq;
		}
		/*
		 * START TRANSACTION
		 * SELECT counter_field FROM child_codes FOR UPDATE;
		  UPDATE child_codes SET counter_field = counter_field + 1;
		 * COMMIT
		 */
		$modSeq = (new Query())
						->selectSingleValue("highestModSeq")
						->from("core_entity")
						->where(["id" => $this->id])
						->forUpdate()
						->single();
		$modSeq++;

		App::get()->getDbConnection()
						->update(
										"core_entity", 
										['highestModSeq' => $modSeq],
										["id" => $this->id]
						)->execute(); //mod seq is a global integer that is incremented on any entity update
	
		$this->modSeqIncremented = true;
		
		$this->highestModSeq = $modSeq;
		
		return $modSeq;
	}	
	
	/**
	 * Get the modification sequence
	 * 
	 * @param string $entityClass
	 * @return int
	 */
	public function nextUserModSeq() {
		
		if($this->userModSeqIncremented) {
			return $this->getHighestUserModSeq();
		}
		
		$modSeq = $this->getHighestUserModSeq();
		$modSeq++;

		App::get()->getDbConnection()
						->replace(
										"core_change_user_modseq", 
										[
												'highestModSeq' => $modSeq,
												"entityTypeId" => $this->id,
												"userId" => GO()->getUserId()
										]
						)->execute(); //mod seq is a global integer that is incremented on any entity update
	
		$this->userModSeqIncremented = true;
		
		$this->highestUserModSeq = $modSeq;
		
		return $modSeq;
	}	
}
