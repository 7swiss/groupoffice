<?php

namespace go\modules\core\customfields\type;

class Date extends Base {

	/**
	 * Get column definition for SQL
	 * 
	 * @return string
	 */
	protected function getFieldSQL() {
		$d = $this->field->getDefault();
		$d = isset($d) && $d != "" ? GO()->getDbConnection()->getPDO()->quote($d) : "NULL";
		return "DATE DEFAULT " . $d;
	}
}
