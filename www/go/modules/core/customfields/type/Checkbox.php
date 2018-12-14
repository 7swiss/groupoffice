<?php

namespace go\modules\core\customfields\type;

class Checkbox extends Base {

	protected function getFieldSQL() {
		$d = empty($this->field->getDefault()) ? "0" : "1";
		return "BOOLEAN NOT NULL DEFAULT '$d'";
	}
}
