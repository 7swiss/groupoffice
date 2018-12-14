<?php

namespace go\modules\core\search\model;

use go\core\acl\model\Acl;
use go\core\acl\model\AclOwnerEntity;
use go\core\db\Criteria;
use go\core\orm\EntityType;
use go\core\orm\Query;
use go\core\util\DateTime;
use go\core\util\StringUtil;

class Search extends AclOwnerEntity {

	public $id;
	public $entityId;
	protected $entityTypeId;
	/**
	 * @var EntityType
	 */
	protected $entity;
	protected $moduleId;
	
	public function getEntity() {
		return $this->entity;
	}
	
	public function setAclId($aclId) {
		$this->aclId = $aclId;
	}
	
	//don't delete acl on search
	protected function deleteAcl() {
		
	}
	
	
	/**
	 * Set the entity type
	 * 
	 * @param string|EntityType $entity "note" or entitytype instance
	 */
	public function setEntity($entity) {
		
		if(!($entity instanceof EntityType)) {
			$entity = EntityType::findByName($entity);
		}	
		$this->entity = $entity->getName();
		$this->entityTypeId = $entity->getId();
		$this->moduleId = $entity->getModuleId();
	}

	public $name;
	public $description;
	public $filter;
	protected $keywords;

	public function setKeywords($keywords) {
		$this->keywords = $keywords;
	}
	
	protected function internalValidate() {
		
		$this->name = StringUtil::cutString($this->name, $this->getMapping()->getColumn('name')->length, false);
		$this->description = StringUtil::cutString($this->description, $this->getMapping()->getColumn('description')->length);		
		$this->keywords = StringUtil::cutString($this->keywords, $this->getMapping()->getColumn('description')->length, true, "");
		
		return parent::internalValidate();
	}

	/**
	 *
	 * @var DateTime
	 */
	public $modifiedAt;

	protected static function defineMapping() {
		return parent::defineMapping()
										->addTable('core_search', 's')
										->setQuery(
														(new Query())
														->select("e.clientName AS entity")
														->join('core_entity', 'e', 'e.id = s.entityTypeId')
		);
	}

	/**
	 * Applies conditions to the query so that only entities with the given permission level are fetched.
	 * 
	 * @param Query $query
	 * @param int $level
	 */
	public static function applyAclToQuery(Query $query, $level = Acl::LEVEL_READ, $userId = null) {
		Acl::applyToQuery($query, 's.aclId', $level, $userId);
		
		return $query;
	}
	
	protected static function defineFilters() {
		return parent::defineFilters()
						->add('entityId', function (Query $query, $value, array $filter){
							$query->where(['entityId' => $value]);		
						})
						->add('entities', function (Query $query, $value, array $filter){
							// Entity filter consist out of name => "Contact" and an optional "filter" => "isOrganization"
							if(empty($value)) {
								return;
							}
							
							$sub = (new Criteria);

							foreach($value as $e) {
								$w = ['e.name' => $e['name']];
								if(isset($e['filter'])) {
									$w['filter'] = $e['filter'];
								}

								$sub->orWhere($w);
							}

							$query->where($sub);		
							
						});
					
	}
	
	
	protected static function searchColumns() {
		return ['keywords'];
	}
	
}
