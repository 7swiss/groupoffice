<?php
namespace go\modules\community\addressbook\model;

use Exception;
use go\core\acl\model\AclItemEntity;
use go\core\db\Column;
use go\core\db\Criteria;
use go\core\model\Link;
use go\core\orm\CustomFieldsTrait;
use go\core\orm\LoggingTrait;
use go\core\orm\Query;
use go\core\orm\SearchableTrait;
use go\core\validate\ErrorCode;
use go\modules\community\addressbook\convert\Csv;
use go\modules\community\addressbook\convert\VCard;
use function GO;
use go\core\mail\Message;
use go\core\TemplateParser;
use go\core\db\Expression;

/**
 * Contact model
 *
 * @copyright (c) 2018, Intermesh BV http://www.intermesh.nl
 * @author Merijn Schering <mschering@intermesh.nl>
 * @license http://www.gnu.org/licenses/agpl-3.0.html AGPLv3
 */

class Contact extends AclItemEntity {
	
	use CustomFieldsTrait;
	
	use SearchableTrait;
	
	use LoggingTrait;
	
	/**
	 * 
	 * @var int
	 */							
	public $id;

	/**
	 * 
	 * @var int
	 */							
	public $addressBookId;
	
	/**
	 * If this contact belongs to a user then this is set to the user ID.
	 * 
	 * @var int 
	 */
	public $goUserId;

	/**
	 * 
	 * @var int
	 */							
	public $createdBy;
	
	/**
	 *
	 * @var int 
	 */
	public $modifiedBy;

	/**
	 * 
	 * @var \IFW\Util\DateTime
	 */							
	public $createdAt;

	/**
	 * 
	 * @var \IFW\Util\DateTime
	 */							
	public $modifiedAt;

	/**
	 * Prefixes like 'Sir'
	 * @var string
	 */							
	public $prefixes = '';

	/**
	 * 
	 * @var string
	 */							
	public $firstName = '';

	/**
	 * 
	 * @var string
	 */							
	public $middleName = '';

	/**
	 * 
	 * @var string
	 */							
	public $lastName = '';

	/**
	 * Suffixes like 'Msc.'
	 * @var string
	 */							
	public $suffixes = '';

	/**
	 * M for Male, F for Female or null for unknown
	 * @var string
	 */							
	public $gender;

	/**
	 * 
	 * @var string
	 */							
	public $notes;

	/**
	 * 
	 * @var bool
	 */							
	public $isOrganization = false;
	
	/**
	 * The job title
	 * 
	 * @var string 
	 */
	public $jobTitle;

	/**
	 * name field for companies and contacts. It should be the display name of first, middle and last name
	 * @var string
	 */							
	public $name;

	/**
	 * 
	 * @var string
	 */							
	public $IBAN = '';

	/**
	 * Company trade registration number
	 * @var string
	 */							
	public $registrationNumber = '';

	/**
	 * 
	 * @var string
	 */							
	public $vatNo;
	
	/**
	 * Don't charge VAT in sender country
	 * 
	 * @var boolean
	 */							
	public $vatReverseCharge = false;

	/**
	 * 
	 * @var string
	 */							
	public $debtorNumber;

	/**
	 * 
	 * @var string
	 */							
	public $photoBlobId;

	/**
	 * 
	 * @var string
	 */							
	public $language;
	
	/**
	 *
	 * @var int
	 */
	public $filesFolderId;
	
	/**
	 *
	 * @var EmailAddress[]
	 */
	 public $emailAddresses = [];
	
	/**
	 *
	 * @var PhoneNumber[]
	 */
	public $phoneNumbers = [];
	
	/**
	 *
	 * @var Date[];
	 */
	public $dates = [];
	
	/**
	 *
	 * @var Url[]
	 */
	public $urls = [];	
	
	/**
	 *
	 * @var ContactOrganization[]
	 */
	public $employees = [];
	
	
	/**
	 *
	 * @var Address[]
	 */
	public $addresses = [];	
	
	/**
	 *
	 * @var ContactGroup[] 
	 */
	public $groups = [];
	
	
	/**
	 * Starred by the current user or not.
	 * 
	 * Should not be false but null for ordering. Records might be missing.
	 * 
	 * @var boolean 
	 */
	protected $starred = null;

	public function getStarred() {
		return !!$this->starred;
	}

	public function setStarred($starred) {
		$this->starred = empty($starred) ? null : true;
	}
	
	
	/**
	 * Universal unique identifier.
	 * 
	 * Either set by sync clients or generated by group-office "<id>@<hostname>"
	 * 
	 * @var string 
	 */
	protected $uid;
	
	/**
	 * Blob ID of the last generated vcard
	 * 
	 * @var string 
	 */
	public $vcardBlobId;	
	
	/**
	 * CardDAV uri for the contact
	 * 
	 * @var string
	 */
	protected $uri;
	
	
	protected static function aclEntityClass(): string {
		return AddressBook::class;
	}

	protected static function aclEntityKeys(): array {
		return ['addressBookId' => 'id'];
	}
	
	protected static function defineMapping() {
		return parent::defineMapping()
						->addTable("addressbook_contact", 'c')
						->addUserTable("addressbook_contact_star", "s", ['id' => 'contactId'])
						->addArray('dates', Date::class, ['id' => 'contactId'])
						->addArray('phoneNumbers', PhoneNumber::class, ['id' => 'contactId'])
						->addArray('emailAddresses', EmailAddress::class, ['id' => 'contactId'])
						->addArray('addresses', Address::class, ['id' => 'contactId'])
						->addArray('urls', Url::class, ['id' => 'contactId'])
						->addArray('groups', ContactGroup::class, ['id' => 'contactId']);						
	}
	
	public function setNameFromParts() {
		$this->name = $this->firstName;
		if(!empty($this->middleName)) {
			$this->name .= " ".$this->middleName;
		}
		if(!empty($this->lastName)) {
			$this->name .= " ".$this->lastName;
		}
		
		$this->name = trim($this->name);
	}
	
	/**
	 * Find contact for user ID.
	 * 
	 * A contact can optionally be connected to a user. It's not guaranteed that
	 * the contact is present.
	 * 
	 * @param int $userId
	 * @return static
	 */
	public static function findForUser($userId, $properties = []) {
		if(empty($userId)) {
			return false;
		}
		return static::find($properties)->where('goUserId', '=', $userId)->single();
	}
	
	/**
	 * Find contact by e-mail address
	 * 
	 * @param string $email
	 * @return Query
	 */
	public static function findByEmail($email) {
		return static::find()
						->join("addressbook_email_address", "e", "e.contactId = c.id")
						->groupBy(['c.id'])
						->where('e.email', '=', $email);
	}
	
	
	/**
	 * Find contact by e-mail address
	 * 
	 * @param string $email
	 * @return Query
	 */
	public static function findByPhone($email) {
		return static::find()
						->join("addressbook_phone_number", "e", "e.contactId = c.id")
						->groupBy(['c.id'])
						->where('e.email', '=', $email);
	}
	
	protected static function defineFilters() {

		return parent::defineFilters()
										->add("addressBookId", function(Criteria $criteria, $value) {
											$criteria->andWhere('addressBookId', '=', $value);
										})
										->add("groupId", function(Criteria $criteria, $value, Query $query) {
											$query->join('addressbook_contact_group', 'g', 'g.contactId = c.id');
											
											$criteria->andWhere('g.groupId', '=', $value);
										})
										->add("isOrganization", function(Criteria $criteria, $value) {
											$criteria->andWhere('isOrganization', '=', $value);
										})
										->add("hasEmailAddresses", function(Criteria $criteria, $value, Query $query) {
											$query->join('addressbook_email_address', 'e', 'e.contactId = c.id', "LEFT")
											->groupBy(['c.id'])
											->having('count(e.email) '.($value ? '>' : '=').' 0');
										})
										->addText("email", function(Criteria $criteria, $comparator, $value, Query $query) {
											$query->join('addressbook_email_address', 'e', 'e.contactId = c.id', "INNER");
											
											$criteria->where('e.email', $comparator, $value);
										})
										->addText("name", function(Criteria $criteria, $comparator, $value) {											
											$criteria->where('name', $comparator, $value);
										})
										->addText("phone", function(Criteria $criteria, $comparator, $value, Query $query) {												
											if(!$query->isJoined('addressbook_phone')) {
												$query->join('addressbook_phone_number', 'phone', 'phone.contactId = c.id', "INNER");
											}
											
											$criteria->where('phone.number', $comparator, $value);
											
										})
										->addText("country", function(Criteria $criteria, $comparator, $value, Query $query) {												
											if(!$query->isJoined('addressbook_address')) {
												$query->join('addressbook_address', 'adr', 'adr.contactId = c.id', "LEFT");
											}
											
											$criteria->where('adr.country', $comparator, $value);
											
										})
										->addText("org", function(Criteria $criteria, $comparator, $value, Query $query) {												
											if( !$query->isJoined('addressbook_contact', 'org')) {
												$query->join('core_link', 'l', 'c.id=l.fromId and l.fromEntityTypeId = '.self::entityType()->getId())						
													->join('addressbook_contact', 'org', 'org.id=l.toId AND l.toEntityTypeId=' . self::entityType()->getId() . ' AND org.isOrganization=true');
											}
											$criteria->where('org.name', $comparator, $value);
											
										})
										->addText("city", function(Criteria $criteria, $comparator, $value, Query $query) {
											if(!$query->isJoined('addressbook_address')) {
												$query->join('addressbook_address', 'adr', 'adr.contactId = c.id', "LEFT");
											}
											
											$criteria->where('adr.city', $comparator, $value);
										})
										->addNumber("age", function(Criteria $criteria, $comparator, $value, Query $query) {
											
											if(!$query->isJoined('addressbook_date')) {
												$query->join('addressbook_date', 'date', 'date.contactId = c.id', "LEFT");
											}
											
											$criteria->where('date.type', '=', Date::TYPE_BIRTHDAY);					
											$tag = ':age'.uniqid();
											$criteria->andWhere('TIMESTAMPDIFF(YEAR,date.date, CURDATE()) ' . $comparator . $tag)->bind($tag, $value);
											
										})
										->add('gender', function(Criteria $criteria, $value) {
											$criteria->andWhere(['gender' => $value, 'isOrganization'=> false]);
										})
										->addDate("birthday", function(Criteria $criteria, $comparator, $value, Query $query) {
											if(!$query->isJoined('addressbook_date')) {
												$query->join('addressbook_date', 'date', 'date.contactId = c.id', "INNER");
											}
											
											$tag = ':bday'.uniqid();
											$criteria->where('date.type', '=', Date::TYPE_BIRTHDAY)
																->andWhere('DATE_ADD(date.date, 
																		INTERVAL YEAR(CURDATE())-YEAR(date.date)
																						 + IF(DAYOFYEAR(CURDATE()) > DAYOFYEAR(date.date),1,0)
																		YEAR)  
																' . $comparator . $tag)->bind($tag, $value->format(Column::DATE_FORMAT));
										})->add('userGroupId', function(Criteria $criteria, $value, Query $query) {
											$query->join('core_user_group', 'ug', 'ug.userId = c.goUserId');
											$criteria->where(['ug.groupId' => $value]);
										})->add('isUser', function(Criteria $criteria, $value, Query $query) {
											$criteria->where('c.goUserId', empty($value) ? '=' : '!=', null);
											
										});
													
										
	}

	public static function sort(\go\core\orm\Query $query, array $sort)
	{
		if(isset($sort['firstName'])) {
			$sort['name'] = $sort['firstName'];
			unset($sort['firstName']);
		}
		if(isset($sort['lastName'])) {
			$dir = $sort['lastName'] == 'ASC' ? 'ASC' : 'DESC';
			$sort[] = new Expression("IF(c.isOrganization, c.name, c.lastName) " . $dir);
			unset($sort['lastName']);
		}
		
		return parent::sort($query, $sort);
	}
	
	public static function converters() {
		$arr = parent::converters();
		$arr['text/vcard'] = VCard::class;		
		$arr['text/csv'] = Csv::class;
		return $arr;
	}

	protected static function textFilterColumns() {
		return ['name', 'debtorNumber'];
	}
	
	public function getUid() {
		
		if(!isset($this->uid)) {
			$url = trim(GO()->getSettings()->URL, '/');
			$uid = substr($url, strpos($url, '://') + 3);
			$uid = str_replace('/', '-', $uid );
			$this->uid = $this->id . '@' . $uid;
		}

		return $this->uid;		
	}

	public function setUid($uid) {
		$this->uid = $uid;
	}

	public function hasUid() {
		return !empty($this->uid);
	}

	public function getUri() {
		if(!isset($this->uri)) {
			$this->uri = $this->getUid() . '.vcf';
		}

		return $this->uri;
	}

	public function setUri($uri) {
		$this->uri = $uri;
	}
		
	protected function internalSave() {
		if(!parent::internalSave()) {
			return false;
		}
		
		if(!isset($this->uid)) {
			//We need the auto increment ID for the UID so we need to save again if this is a new contact
			$this->getUid();
			$this->getUri();

			if(!GO()->getDbConnection()
							->update('addressbook_contact', 
											['uid' => $this->uid, 'uri' => $this->uri], 
											['id' => $this->id])
							->execute()) {
				return false;
			}
		}		
		
		return $this->saveOriganizationIds();
		
	}
	
	protected function internalValidate() {		
		
		if(empty($this->name)) {
			$this->setNameFromParts();
		}		
		
		if($this->isNew() && !isset($this->addressBookId)) {
			$this->addressBookId = GO()->getAuthState()->getUser()->addressBookSettings->defaultAddressBookId;
		}
		
		if($this->isModified('addressBookId') || $this->isModified('groups')) {
			//verify groups and address book match
			
			foreach($this->groups as $group) {
				$group = Group::findById($group->groupId);
				if($group->addressBookId != $this->addressBookId) {
					$this->setValidationError('groups', ErrorCode::INVALID_INPUT, "The contact groups must match with the addressBookId. Group ID: ".$group->id." belongs to ".$group->addressBookId." and the contact belongs to ". $this->addressBookId);
				}
			}
		}
		
		return parent::internalValidate();
	}
	
	/**
	 * Find all linked organizations
	 * 
	 * @return self[]
	 */
	public function findOrganizations(){
		return self::find()
						->join('core_link', 'l', 'c.id=l.toId and l.toEntityTypeId = '.self::entityType()->getId())
						->where('fromId', '=', $this->id)
							->andWhere('fromEntityTypeId', '=', self::entityType()->getId())
							->andWhere('c.isOrganization', '=', true);
	}
	
	private $organizationIds;
	private $setOrganizationIds;
	
	public function getOrganizationIds() {

		if(!isset($this->organizationIds)) {			
			if($this->isNew()) {
				$this->organizationIds = [];
			} else {
				$query = $this->findOrganizations()->selectSingleValue('c.id');			
				$this->organizationIds = array_map("intval", $query->all());
			}
		}		
		
		return $this->organizationIds;
	}
	
	public function setOrganizationIds($ids) {		
		$this->setOrganizationIds = $ids;				
	}
	
	private function saveOriganizationIds(){
		if(!isset($this->setOrganizationIds)) {
			return true;
		}
		$current = $this->getOrganizationIds();
		
		$remove = array_diff($current, $this->setOrganizationIds);
		if(count($remove)) {
			Link::deleteLinkWithIds($remove, Contact::entityType()->getId(), $this->id, Contact::entityType()->getId());
		}
		
		$add = array_diff($this->setOrganizationIds, $current);
		
		foreach($add as $orgId) {
			$org = self::findById($orgId);
			if(!Link::create($this, $org)) {
				throw new Exception("Failed to link organization: ". $orgId);
			}
		}

		$this->organizationIds = $this->setOrganizationIds;
		return true;
	}

	protected function getSearchDescription() {
		$addressBook = AddressBook::findById($this->addressBookId);
		
		$orgStr = "";	
		
		if(!$this->isOrganization) {
			$orgs = $this->findOrganizations()->selectSingleValue('name')->all();
			if(!empty($orgs)) {
				$orgStr = ' - '.implode(', ', $orgs);			
			}
		}
		return $addressBook->name . $orgStr;
	}

	protected function getSearchName() {
		return $this->name;
	}

	protected function getSearchFilter() {
		return $this->isOrganization ? 'isOrganization' : 'isContact';
	}

	protected function getSearchKeywords()
	{
		$keywords = [$this->name, $this->debtorNumber];
		foreach($this->emailAddresses as $e) {
			$keywords[] = $e->email;
		}
		if(!$this->isOrganization) {
			$keywords = array_merge($keywords, $this->findOrganizations()->selectSingleValue('name')->all());
		}

		return $keywords;
	}

	public function getSalutation() 
	{
		$tpl = new TemplateParser();
		$tpl->addModel('contact', $this->toArray(['firstName', 'lastName', 'middleName', 'name', 'gender', 'prefixes', 'suffixes', 'language']));

		$user = GO()->getAuthState()->getUser(['addressBookSettings']);

		if(!isset($user->addressBookSettings)){
			$user->addressBookSettings = new UserSettings();
		}

		return $tpl->parse($user->addressBookSettings->salutationTemplate);
	}
	
	/**
	 * Because we've implemented the getter method "getOrganizationIds" the contact 
	 * modSeq must be incremented when a link between two contacts is deleted or 
	 * created.
	 * 
	 * @param Link $link
	 */
	public static function onLinkSaveOrDelete(Link $link) {
		if($link->getToEntity() !== "Contact" || $link->getFromEntity() !== "Contact") {
			return;
		}
		
		$to = Contact::findById($link->toId);
		$from = Contact::findById($link->fromId);
		
		//Save contact as link to organizations affect the search entities too.
		if(!$to->isOrganization) {			
			$to->saveSearch();
			Contact::entityType()->change($to);
		}
		
		if(!$from->isOrganization) {			
			$from->saveSearch();
			Contact::entityType()->change($from);
		}
		
//		$ids = [$link->toId, $link->fromId];
//		
//		//Update modifiedAt dates for Z-Push and carddav
//		GO()->getDbConnection()
//						->update(
//										'addressbook_contact',
//										['modifiedAt' => new DateTime()], 
//										['id' => $ids]
//										)->execute();	
//		
//		Contact::entityType()->changes(
//					(new Query2)
//					->select('c.id AS entityId, a.aclId, "0" AS destroyed')
//					->from('addressbook_contact', 'c')
//					->join('addressbook_addressbook', 'a', 'a.id = c.addressBookId')					
//					->where('c.id', 'IN', $ids)
//					);
		
	}
	
	
	/**
	 * Find URL by type
	 * 
	 * @param string $type
	 * @param boolean $returnAny
	 * @return EmailAddress|boolean
	 */
	public function findUrlByType($type, $returnAny = true) {
		return $this->findPropByType("urls", $type, $returnAny);
	}

	
	/**
	 * Find email address by type
	 * 
	 * @param string $type
	 * @param boolean $returnAny
	 * @return EmailAddress|boolean
	 */
	public function findEmailByType($type, $returnAny = true) {
		return $this->findPropByType("emailAddresses", $type, $returnAny);
	}
	
	/**
	 * Find phoneNumber by type
	 * 
	 * @param string $type
	 * @param boolean $returnAny
	 * @return PhoneNumbers|boolean
	 */
	public function findPhoneNumberByType($type, $returnAny = true) {
		return $this->findPropByType("phoneNumbers", $type, $returnAny);
	}
	
	/**
	 * Find street address by type
	 * 
	 * @param string $type
	 * @param boolean $returnAny
	 * @return Address|boolean
	 */
	public function findAddressByType($type, $returnAny = true) {
		return $this->findPropByType("addresses", $type, $returnAny);
	}
	
	/**
	 * Find date by type
	 * 
	 * @param string $type
	 * @param boolean $returnAny
	 * @return Date|boolean
	 */
	public function findDateByType($type, $returnAny = true) {
		return $this->findPropByType("dates", $type, $returnAny);
	}
	
	private function findPropByType($propName, $type, $returnAny) {
		foreach($this->$propName as $prop) {
			if($prop->type === $type) {
				return $prop;
			}
		}
		
		if(!$returnAny) {
			return false;
		}
		
		return isset($this->$propName[0]) ? $this->$propName[0] : false;
	}

	/**
	 * Decorate the message for newsletter sending.
	 * This function should at least add the to address.
	 * 
	 * @param \Swift_Message $message
	 */
	public function decorateMessage(Message $message) {
		if(!isset($this->emailAddresses[0])) {
			return false;
		}
		$message->setTo($this->emailAddresses[0]->email, $this->name);
	}

	public function toTemplate() {
		$array = parent::toTemplate();
		$array['organizations'] = $this->findOrganizations()->all();

		return $array;
	}
}
