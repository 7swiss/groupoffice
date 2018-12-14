<?php
namespace go\modules\core\customfields\install;

use go\core\db\Query;
use go\core\db\Utils;
use go\modules\core\customfields\model\Field;
use go\modules\core\customfields\model\FieldSet;
use PDOException;
use function GO;

class Migrate63to64 {	
	
	public function migrateEntity($entityName) {
		
		$entityType = \go\core\orm\EntityType::findByName($entityName);
		
		if(!$entityType) {
			echo "Entity type: ". $entityName . " not found. Skipping.\n";
			return;
		}
		
		$fields = Field::findByEntity($entityType->getId());

		foreach ($fields as $field) {
			
			switch ($field->type) {
				case "Select":
						if($field->getOption('multiselect')) {
							$this->updateMultiSelect($field);
						} else
						{
							$this->updateSingleSelect($field);
						}
					break;

				case "Treeselect":
						$this->updateTreeSelect($field);
					break;
				
				case "User":
					$this->updateSelectEntity($field, \go\modules\core\users\model\User::class);
					break;
				
				case "Group":
					$this->updateSelectEntity($field, \go\modules\core\groups\model\Group::class);
					break;
				
				case "Textarea":
					$field->type = "TextArea";
					$field->save();
					break;
				
				case "Datetime":
					$field->type = "DateTime";
					$field->save();
					break;
				
				case "Heading":
				case "Infotext":
				case "ReadonlyText":
					$field->type = "Notes";
					$field->setOption("formNotes", $field->name);
					$field->save();
					break;
			}
		}
		
//		exit("STOP FOR TEST");
	}
	
	public function updateSelectEntity(Field $field, $entityCls, $incrementID = 0) {		
		
		$query = $this->findRecords($field);		
		foreach($query as $record) {
			//Value is string <id>:<Text>
			$id = explode(':', $record[$field->databaseName])[0];
			
			GO()->getDbConnection()
								->update(
												$field->tableName(), 
												[$field->databaseName => $id + $incrementID],
												['id' => $record['id']]
												)->execute();
		}
		
		$validIds = $entityCls::find()->selectSingleValue('id');
		
		//for changing db column
		$field->setDefault(null);
		if(!$field->save()) {
			throw new \Exception("Couldn't save field: ".var_export($field->getValidationErrors()));
		}
		
		//nullify invalid records
		GO()->getDbConnection()->update(
						$field->tableName(), 
						[$field->databaseName => null],
						(new Query)->where($field->databaseName, 'NOT IN', $validIds)
						)->execute();
		
		try {			
			$field->getDataType()->addConstraint();
		} catch(PDOException $e) {			
			//ignore duplicates
		}
	}
	
	private function findRecords(Field $field) {
		return GO()->getDbConnection()->select("id, `" . $field->databaseName . "`")
						->from($field->tableName())
						->where($field->databaseName, '!=', "")
						->andWhere($field->databaseName, 'IS NOT', null);
	}
	
	
	public function convertTypeNames() {
		
		$fields = Field::find();
		
		foreach ($fields as $field) {
			$parts = explode('\\', $field->type);
			$type = array_pop($parts);
			
			if($type == "UserGroup") {
				$type = "Group";
			}
			
			//Use DBAL because entity will alter database and we don't need that here.
			GO()->getDbConnection()
							->update(
											'core_customfields_field', 
											['type' => $type], 
											['id' => $field->id]
											)->execute();
		}
	}
	
	private function updateSingleSelect(Field $field) {
		$selectOptions = $field->getDataType()->getOptions();		
		
		foreach($selectOptions as $o) {			
			GO()->getDbConnection()
							->update($field->tableName(), [$field->databaseName => $o['id']], [$field->databaseName => $o['text']])->execute();	
		}		
		
		//for changing db column
		$field->setDefault(null);
		$field->save();
		
		$this->nullifyInvalidOptions($field);
		try {			
			$field->getDataType()->addConstraint();
		} catch(PDOException $e) {			
			//ignore duplicates
		}
	}
	
	private function updateMultiSelect(Field $field) {
		$field->type = "MultiSelect";		
		try{
			$field->getDataType()->createMultiSelectTable();
		}catch(\PDOException $e) {
			//ignore already existing
		}
		
		
		$options = GO()->getDbConnection()
						->select("*")
						->from("core_customfields_select_option")
						->where('fieldId', '=', $field->id)->all();
		
		foreach($options as $o) {
			$optionMap[$o['text']] = $o['id'];
		}
		
		$query = $this->findRecords($field);
		
		foreach($query as $record){
			$values = explode("|", $record[$field->databaseName]);
			
			foreach($values as $value) {
				if(!isset($optionMap[$value])) {
					continue;
				}
				
				GO()->getDbConnection()
								->replace(
												$field->getDataType()->getMultiSelectTableName(), 
												['id' => $record['id'], 'optionId' => $optionMap[$value]]
												)->execute();
			}
		}		
		$field->save();
		
		//remove column because it's stored in linking table
		$sql = "ALTER TABLE `" . $field->tableName() . "` DROP " . Utils::quoteColumnName($field->databaseName) ;
		GO()->getDbConnection()->query($sql);
	}
	
	
	private function findSlaveFields(Field $field) {
		$allSlaves = Field::find()->where(['type' => 'TreeselectSlave'])->all();
		$treeSlaves = array_filter($allSlaves, function($slave) use ($field) {
			return $slave->getOption('treeMasterFieldId') == $field->id;
		});
		
		$fields[0] = $field;		
		foreach($treeSlaves as $slave) {
			$fields[$slave->getOption("nestingLevel")] = $slave;
		}
		ksort($fields);
		
		return $fields;
	}
	
	
	const TREE_SELECT_OPTION_INCREMENT = 100000;
	
	/**
	 * 
	 * @param type $record
	 * @param Field[] $fields
	 */
	private function findSelectOptionId($record, array $fields) {
		//find value with highest nesting level
		$v = null;
		foreach($fields as $field) {
			if(!empty($record[$field->databaseName])) {
				$v = $record[$field->databaseName];
			}
		}

		//Value is string <id>:<Text>
		$id = explode(':', $v)[0];
		
		return $id + self::TREE_SELECT_OPTION_INCREMENT;
	}
	
	private function findTreeSelectRecords(Field $field, array $fields) {
		$query = GO()->getDbConnection()->select()
						->from($field->tableName());		
		foreach($fields as $field) {
			$query->orWhere($field->databaseName, '!=', "");
		}
		
		return $query;
	}
	
	private function convertTreeSelectOptions(Field $field) {
		$ids = GO()->getDbConnection()->selectSingleValue('id')->from("cf_tree_select_options")->all();
		$ids[] = "0";
		
		$oldOptions = GO()->getDbConnection()
						->select()
						->from('cf_tree_select_options')
						->where('field_id', '=', $field->id)
						->andWhere('parent_id', 'IN', $ids)
						->orderBy(['parent_id'=>'ASC']);
		
		foreach($oldOptions as $o) {
			GO()->getDbConnection()
							->insertIgnore("core_customfields_select_option", [
									'id' => $o['id'] + self::TREE_SELECT_OPTION_INCREMENT,
									'fieldId' => $field->id,
									'parentId' => !empty($o['parent_id']) ? $o['parent_id'] + self::TREE_SELECT_OPTION_INCREMENT : null,
									'text' => $o['name']							
							])->execute();
		}
	}
	
	private function updateTreeSelect(Field $field) {			
		
		$this->convertTreeSelectOptions($field);
		
		$fields = $this->findSlaveFields($field);
		foreach($this->findTreeSelectRecords($field, $fields) as $record) {			
			
			GO()->debug($record);
			
			$id = $this->findSelectOptionId($record, $fields);			
			
			GO()->debug($id);
			
			GO()->getDbConnection()
							->update(
											$field->tableName(),
											[$field->databaseName => $id], 
											['id' => $record['id']]
											)->execute();
		}		
		
		$field->type = "Select";
		$field->setDefault(null);
		$field->save();
		
		$this->nullifyInvalidOptions($field);
		try {
			$field->getDataType()->addConstraint();
		} catch(PDOException $e) {			
			//ignore duplicates
		}
		
		//delete slaves
		array_shift($fields);
		foreach($fields as $field) {
			$field->type = "Text";
			$field->delete();
		}
	}
	
	
	private function nullifyInvalidOptions(Field $field) {
		//set invalid options to null
		$optionIds = GO()->getDbConnection()
						->selectSingleValue('id')
						->from("core_customfields_select_option")
						->where('fieldId', '=', $field->id);
		
		GO()->getDbConnection()->update(
						$field->tableName(), 
						[$field->databaseName => null], 
						(new Query)
						->where($field->databaseName, 'NOT IN', $optionIds)
						)->execute();
	}
}
