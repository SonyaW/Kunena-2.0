<?php
/**
 * Kunena Component
 * @package Kunena.Framework
 * @subpackage Forum.Topic.User
 *
 * @copyright (C) 2008 - 2012 Kunena Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 **/
defined ( '_JEXEC' ) or die ();

/**
 * Kunena Forum Topic User Class
 */
class KunenaForumTopicUser extends JObject {
	protected $_exists = false;
	protected $_db = null;

	/**
	 * Constructor
	 *
	 * @access	protected
	 */
	public function __construct($topic = null, $user = null) {
		$topic = KunenaForumTopicHelper::get($topic);

		// Always fill empty data
		$this->_db = JFactory::getDBO ();

		// Create the table object
		$table = $this->getTable ();

		// Lets bind the data
		$this->setProperties ( $table->getProperties () );
		$this->_exists = false;
		$this->topic_id = $topic->exists() ? $topic->id : null;
		$this->category_id = $topic->exists() ? $topic->category_id : null;
		$this->user_id = KunenaUserHelper::get($user)->userid;
	}

	static public function getInstance($id = null, $user = null, $reload = false) {
		return KunenaForumTopicUserHelper::get($id, $user, $reload);
	}

	public function getTopic() {
		return KunenaForumTopicHelper::get($this->topic_id);
	}

	function exists($exists = null) {
		$return = $this->_exists;
		if ($exists !== null) $this->_exists = $exists;
		return $return;
	}

	/**
	 * Method to get the topics table object
	 *
	 * This function uses a static variable to store the table name of the user table to
	 * it instantiates. You can call this function statically to set the table name if
	 * needed.
	 *
	 * @access	public
	 * @param	string	The topics table name to be used
	 * @param	string	The topics table prefix to be used
	 * @return	object	The topics table object
	 * @since	1.6
	 */
	public function getTable($type = 'KunenaUserTopics', $prefix = 'Table') {
		static $tabletype = null;

		//Set a custom table type is defined
		if ($tabletype === null || $type != $tabletype ['name'] || $prefix != $tabletype ['prefix']) {
			$tabletype ['name'] = $type;
			$tabletype ['prefix'] = $prefix;
		}

		// Create the user table object
		return JTable::getInstance ( $tabletype ['name'], $tabletype ['prefix'] );
	}

	public function bind($data, $ignore = array()) {
		$data = array_diff_key($data, array_flip($ignore));
		$this->setProperties ( $data );
	}

	public function reset() {
		$this->topic_id = 0;
		$this->load();
	}

	/**
	 * Method to load a KunenaForumTopicUser object by id
	 *
	 * @access	public
	 * @param	mixed	$id The topic id to be loaded
	 * @return	boolean			True on success
	 * @since 1.6
	 */
	public function load($topic_id = null, $user = null) {
		if ($topic_id === null) {
			$topic_id = $this->topic_id;
		}
		if ($user === null && $this->user_id !== null) {
			$user = $this->user_id;
		}
		$user = KunenaUserHelper::get($user);

		// Create the table object
		$table = $this->getTable ();

		// Load the KunenaTable object based on id
		if ($topic_id) $this->_exists = $table->load ( array('user_id'=>$user->userid, 'topic_id'=>$topic_id) );
		else $this->_exists = false;

		// Assuming all is well at this point lets bind the data
		$this->setProperties ( $table->getProperties () );
		return $this->_exists;
	}

	/**
	 * Method to save the KunenaForumTopicUser object to the database
	 *
	 * @access	public
	 * @param	boolean $updateOnly Save the object only if not a new topic
	 * @return	boolean True on success
	 * @since 1.6
	 */
	public function save($updateOnly = false) {
		// Create the topics table object
		$table = $this->getTable ();
		$table->bind ( $this->getProperties () );
		$table->exists ( $this->_exists );

		// Check and store the object.
		if (! $table->check ()) {
			$this->setError ( $table->getError () );
			return false;
		}

		//are we creating a new topic
		$isnew = ! $this->_exists;

		// If we aren't allowed to create new topic return
		if ($isnew && $updateOnly) {
			return true;
		}

		//Store the topic data in the database
		if (! $result = $table->store ()) {
			$this->setError ( $table->getError () );
		}

		// Fill up KunenaForumTopicUser object in case we created a new topic.
		if ($result && $isnew) {
			$this->load ();
		}

		return $result;
	}

	/**
	 * Method to delete the KunenaForumTopicUser object from the database
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.6
	 */
	public function delete() {
		if (!$this->exists()) {
			return true;
		}

		// Create the table object
		$table = $this->getTable ();

		$result = $table->delete ( array('topic_id'=>$this->topic_id, 'user_id'=>$this->user_id) );
		if (! $result) {
			$this->setError ( $table->getError () );
		}
		$this->_exists = false;

		return $result;
	}

	function update($message=null, $postDelta=0) {
		$this->posts += $postDelta;
		$this->category_id = $this->getTopic()->category_id;
		if ($message && !$message->hold && $message->thread == $this->topic_id) {
			if ($message->parent == 0) {
				$this->owner = 1;
			}
			if ($this->last_post_id < $message->id) {
				$this->last_post_id = $message->id;
			}
		} elseif (!$message || (($message->hold || $message->thread != $this->topic_id ) && $this->last_post_id == $message->id)) {
			$query ="SELECT COUNT(*) AS posts, MAX(id) AS last_post_id, MAX(IF(parent=0,1,0)) AS owner
					FROM #__kunena_messages WHERE userid={$this->_db->quote($this->user_id)} AND thread={$this->_db->quote($this->topic_id)} AND moved=0 AND hold=0
					GROUP BY userid, thread";
			$this->_db->setQuery($query, 0, 1);
			$info = $this->_db->loadObject ();
			if (KunenaError::checkDatabaseError ())
				return;
			$this->bind($info);
		}
		return $this->save();
	}
}