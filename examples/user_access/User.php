<?php
class User extends BasicObject {
	protected static function table_name() {
		return 'users';
	}

	public function has_access($code_name) {
		// Build an array with paramaters to BasicObject::count.
		$parameters = array(
			// specify which access.
			'code_name' => $code_name,
			/**
			 * Match all groups that has the access.
			 * The ':match_group' is to differ this from the other @or in this
			 * array, put anything you want afther the ':' to make it distinct.
			 */
			'@or:match_group' => array(
				// The groups access is permanent
				'GroupAccess.permanent' => true,
				/**
				 * Or limited in time.
				 * The ':>=' means that the value should be grater or equal to
				 * the inputed value
				 */
				'GroupAccess.valid_until:>=' => date('Y-m-d'),
			),
			// Match all users that are members in any off the above groups.
			'@or:match_users' => array(
				// permanent member
				'GroupAccess.Group.GroupMember.permanent' => true,
				// limited time member
				'GroupAccess.Group.GroupMember.valid_until:>=' => date('Y-m-d'),
			),
			// Match the users found to the current user
			'GroupAccess.Group.GroupMember.user_id' => $this->id,
		);
		/**
		 * Make the selection and count the result. If more than one row matches
		 * the user has the access.
		 */
		return Access::count($parameters) > 0;
	}
}
?>
