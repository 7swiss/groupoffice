<?php
/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace go\core\oauth\server\entities;
;

use go\core\model\User;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\Entities\ClaimSetInterface;

class UserEntity implements UserEntityInterface, ClaimSetInterface
{
	private $user;
	public function __construct(User $user)
	{
		$this->user = $user;
	}


	/**
	 * Return the user's identifier.
	 *
	 * @return mixed
	 */
	public function getIdentifier()
	{
		return $this->user->id();
	}

	public function getClaims()
	{
		return [
			// profile
			'name' => $this->user->displayName,
			'family_name' => '',
			'given_name' => '',
			'middle_name' => '',
			'nickname' => $this->user->displayName,
			'preferred_username' => $this->user->username,
			'profile' => '',
			'picture' => '',
			'website' => '',
			'gender' => '',
//			'birthdate' => '01/01/1990',
//			'zoneinfo' => '',
//			'locale' => 'US',
			'updated_at' => $this->user->modifiedAt->format('c'),
			// email
			'email' => $this->user->email,
			'email_verified' => true,
			// phone
			'phone_number' => '',
			'phone_number_verified' => true,
			// address
			'address' => '',
		];
	}
}
