<?php
namespace go\modules\core\customfields\type;

use Exception;
use GO;
use go\core\data\Model;
use go\core\db\Table;
use go\core\db\Utils;
use go\core\ErrorHandler;
use go\core\util\ClassFinder;
use go\modules\core\customfields\model\Field;


/**
 * Abstract data type class
 * 
 * The data types handles:
 * 
 * 1. Column creation in database (Override getFieldSql())
 * 2. Input formatting with apiToDb();
 * 3. Output formatting with dbToApi();
 * 
 */
abstract class Base extends Model {
	
	/**
	 *
	 * @var Field 
	 */
	protected $field;
	
	public function __construct(Field $field) {
		$this->field = $field;
	}
	
	/**
	 * Get column definition for SQL.
	 * 
	 * When false is returned no databaseName is required and no field will be created.
	 * 
	 * @return string|boolean
	 */
	protected function getFieldSQL() {
		return "VARCHAR(".($this->field->getOption('maxLength') ?? 190).") DEFAULT " . GO()->getDbConnection()->getPDO()->quote($this->field->getDefault() ?? "NULL");
	}
	
	public function onFieldValidate() {
		$fieldSql = $this->getFieldSQL();
		if(!$fieldSql) {
			return true;
		}
		
		if($this->field->isModified("databaseName") && preg_match('/[^a-zA-Z_0-9]/', $this->field->databaseName)) {
			$this->field->setValidationError('databaseName', \go\core\validate\ErrorCode::INVALID_INPUT, GO()->t("Invalid database name. Only use alpha numeric chars and underscores.", 'core','customfields'));
		}		
	}
	
	/**
	 * Called when the field is saved
	 * 
	 * @return boolean
	 */
	public function onFieldSave() {
		
		$fieldSql = $this->getFieldSQL();
		if(!$fieldSql) {
			return true;
		}
		
		$table = $this->field->tableName();
		
		
		$quotedDbName = Utils::quoteColumnName($this->field->databaseName);
	
		if ($this->field->isNew()) {
			$sql = "ALTER TABLE `" . $table . "` ADD " . $quotedDbName . " " . $fieldSql . ";";
			GO()->getDbConnection()->query($sql);
			if($this->field->getUnique()) {
				$sql = "ALTER TABLE `" . $table . "` ADD UNIQUE(". $quotedDbName  . ");";
				GO()->getDbConnection()->query($sql);
			}			
		} else {
			
			
			$oldName = $this->field->isModified('databaseName') ? $this->field->getOldValue("databaseName") : $this->field->databaseName;
			$col = Table::getInstance($table)->getColumn($oldName);
			
			$sql = "ALTER TABLE `" . $table . "` CHANGE " . Utils::quoteColumnName($oldName) . " " . $quotedDbName . " " . $fieldSql;
			GO()->getDbConnection()->query($sql);
			
			if($this->field->getUnique() && !$col->unique) {
				$sql = "ALTER TABLE `" . $table . "` ADD UNIQUE(". $quotedDbName  . ");";
				GO()->getDbConnection()->query($sql);
			} else if(!$this->field->getUnique() && $col->unique) {
				$sql = "ALTER TABLE `" . $table . "` DROP INDEX " . $quotedDbName;
				GO()->getDbConnection()->query($sql);
			}
		}
		
		Table::getInstance($table)->clearCache();
		
		return true;
	}

	/**
	 * Called when a field is deleted
	 * 
	 * @return boolean
	 */
	public function onFieldDelete() {
		
		$fieldSql = $this->getFieldSQL();
		if(!$fieldSql) {
			return true;
		}
		
		$table = $this->field->tableName();
		$sql = "ALTER TABLE `" . $table . "` DROP " . Utils::quoteColumnName($this->field->databaseName) ;

		try {
			GO()->getDbConnection()->query($sql);
		} catch (Exception $e) {
			ErrorHandler::logException($e);
		}
		
		Table::getInstance($table)->clearCache();
		
		return true;
	}
	
	/**
	 * Format data from API to model
	 * 
	 * This function is called when the API data is applied to the model with setValues();
	 * 
	 * @see MultiSelect for an advaced example
	 * @param mixed $value The value for this field
	 * @param array $values The values to be saved in the custom fields table
	 * @return mixed
	 */
	public function apiToDb($value, &$values) {
		return $value;
	}
	
	/**
	 * Format data from model to API
	 * 
	 * This function is called when the data is serialized to JSON
	 * 
	 * @see MultiSelect for an advaced example
	 * @param mixed $value The value for this field
	 * @param array $values All the values of the custom fields to be returned to the API
	 * @return mixed
	 */
	public function dbToApi($value, &$values) {
		return $value;
	}
	
	/**
	 * Called after the data is saved to API.
	 * 
	 * @see MultiSelect for an advaced example
	 * @param mixed $value The value for this field
	 * @param array $values The values inserted in the database
	 * @return boolean
	 */
	public function afterSave($value, &$values) {
		
		return true;
	}
	
	/**
	 * Called before the data is saved to API.
	 * 
	 * @see MultiSelect for an advaced example
	 * @param mixed $value The value for this field
	 * @param array $values The values inserted in the database
	 * @return boolean
	 */
	public function beforeSave($value, &$values) {
		
		return true;
	}
	
	/**
	 * Get the name of this data type
	 * 
	 * @return string
	 */
	public static function getName() {
		$cls = static::class;
		return substr($cls, strrpos($cls, '\\') + 1);
	}
	
	/**
	 * Get all field types
	 * 
	 * @return string[] eg ['functionField' => "go\modules\core\customfields\type\FunctionField"];
	 */
	public static function findAll() {
		
		$types = GO()->getCache()->get("customfield-types");
		
		if(!$types) {
			$classFinder = new ClassFinder();
			$classes = $classFinder->findByParent(self::class);

			$types = [];

			foreach($classes as $class) {
				$types[$class::getName()] = $class;
			}
			
			if(GO()->getModule(null, "files")) {
				$types['File'] = \GO\Files\Customfield\File::class;
			}
			
			GO()->getCache()->set("customfield-types", $types);
		}
		
		return $types;		
	}
	
	/**
	 * Find the class for a type
	 * 
	 * @param string $name
	 * @return string
	 */
	public static function findByName($name) {
		
		//for compatibility with old version
		//TODO remove when refactored completely
		$pos = strrpos($name, '\\');
		if($pos !== false) {
			$name = lcfirst(substr($name, $pos + 1));
		}
		$all = static::findAll();

		if(!isset($all[$name])) {
			GO()->debug("WARNING: Custom field type '$name' not found");			
			return Text::class;
		}
		
		return $all[$name];
	}
}
