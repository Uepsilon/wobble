<?php
class Topic {
	const TYPE_INLINE = 0;
	const TYPE_INTENDED_REPLY = 1;
}
/**
 * The TopicRepository provides convienience function to access the storage for Topics.
 */
class TopicRepository {
	
	/**
	 * Creates a new topic-post
	 *
	 * @param $inlineReply boolean true to make a continue-thread-reply or false for an intended reply in a new thread
	 */
	public static function createPost($topic_id, $post_id, $user_id, $parent_post_id, $inlineReply = true) {
		$pdo = ctx_getpdo();

		// Create empty root post
		$stmt = $pdo->prepare('INSERT INTO posts (topic_id, post_id, content, parent_post_id, created_at, last_touch, post_type)  VALUES (?,?, "",?, unix_timestamp(), unix_timestamp(), ?)');
		$stmt->execute(array($topic_id, $post_id, $parent_post_id, $inlineReply ? Topic::TYPE_INLINE : Topic::TYPE_INTENDED_REPLY));
		
		// Assoc first post with current user
		$stmt = $pdo->prepare('INSERT INTO post_editors (topic_id, post_id, user_id) VALUES (?,?,?)');
		$stmt->bindValue(1, $topic_id);
		$stmt->bindValue(2, $post_id);
		$stmt->bindValue(3, $user_id);
		
		$stmt->execute();
	}
	public static function deletePost($topic_id, $post_id) {
		$pdo = ctx_getpdo();

		$stmt = $pdo->prepare('DELETE FROM post_editors WHERE topic_id = ? AND post_id = ?');
		$stmt->execute(array($topic_id, $post_id));
		
		$pdo->prepare('UPDATE posts SET deleted = 1, content = NULL WHERE topic_id = ? AND post_id = ?')->execute(array($topic_id, $post_id));

		$pdo->prepare('DELETE FROM post_users_read WHERE topic_id = ? AND post_id = ?')->execute(array($topic_id, $post_id));

		TopicRepository::deletePostsIfNoChilds($topic_id, $post_id);
	}
	/**
	 * Traverses upwards and deletes all posts, if no child exist
	 */
	public static function deletePostsIfNoChilds($topic_id, $post_id) {
		if($post_id === '1') {
			return;
		}
		$pdo = ctx_getpdo();
		
		# How many children do I have?
		$sql = 'SELECT COUNT(*) child_count FROM posts WHERE topic_id = ? AND parent_post_id = ?';
		$stmt = $pdo->prepare($sql);
		$stmt->execute(array($topic_id, $post_id));
		$result = $stmt->fetchAll();
		
		# The post itself has no children? Ok ...
		if ( intval($result[0]['child_count']) === 0) {
			# Who is my daddy?
			$sql = 'SELECT parent_post_id FROM posts WHERE topic_id = ? AND post_id = ? LIMIT 1';
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array($topic_id, $post_id));
			$post = $stmt->fetchAll();

			# Delete the post
			$sql = 'DELETE FROM posts WHERE topic_id = ? AND post_id = ?';
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array($topic_id, $post_id));

			# Try to delete the parent
			if ( count($posts) > 0 ) {
				TopicRepository::deletePostsIfNoChilds($topic_id, $post[0]['parent_post_id']);
			}
		}		
	}
	function addReader($topic_id, $user_id) {
		$pdo = ctx_getpdo();

		$pdo->prepare('REPLACE topic_readers (topic_id, user_id) VALUES (?,?)')->execute(array($topic_id, $user_id));
	}

	function removeReader($topic_id, $user_id) {
		$pdo = ctx_getpdo();
		$pdo->prepare('DELETE FROM topic_readers WHERE topic_id = ? AND user_id = ?')->execute(array($topic_id, $user_id));

		$pdo->prepare('DELETE FROM post_users_read WHERE topic_id = ? AND user_id = ?')->execute(array($topic_id, $user_id));
	}
	function setPostReadStatus($user_id, $topic_id, $post_id, $read_status) {
		$pdo = ctx_getpdo();
		#var_dump($read_status);
		if ( $read_status == 1) { # if read, create entry
			$sql = 'REPLACE post_users_read (topic_id, post_id, user_id) VALUES (?,?,?)';
		} else {
			$sql = 'DELETE FROM post_users_read WHERE topic_id = ? AND post_id = ? AND user_id = ?';
		}
		$pdo->prepare($sql)->execute(array($topic_id, $post_id, $user_id));
	}

	/**
	 * Returns the user objects for every reader of a topic. Readers are the user which are allowed 
	 * to read and write to a topic.
	 */
	function getReaders($topic_id, $limit = FALSE) {
		assert('!empty($topic_id)');
		$pdo = ctx_getpdo();
		
		$sql = 'SELECT distinct u.id id, u.name name, u.email email, md5(u.email) img, COALESCE(last_touch > (UNIX_TIMESTAMP() - 300), false) online ' . 
			  'FROM users u, topic_readers r ' . 
			  'WHERE u.id = r.user_id AND r.topic_id = ?';
		if ( $limit ) {
			$sql .= ' LIMIT ' . $limit;
		}
		$stmt = $pdo->prepare($sql);
		$stmt->execute(array($topic_id));
		$result =  $stmt->fetchAll();
		foreach($result AS $i => $user) {
			$result[$i]['id'] = intval($user['id']); # convert id to int
			$result[$i]['online'] = intval($user['online']); 
		}
		return $result;
	}

	/**
	 * Returns the user objects for every user ever written in a topic. 
	 */
	function getWriters($topic_id, $limit = FALSE) {
		assert('!empty($topic_id)');
		$pdo = ctx_getpdo();
		
		$sql = 'SELECT distinct u.id id, u.name name, u.email email, md5(u.email) img, COALESCE(last_touch > (UNIX_TIMESTAMP() - 300), false) online ' . 
			  'FROM users u, post_editors pe ' . 
			  'WHERE u.id = pe.user_id AND pe.topic_id = ?';
		if ( $limit ) {
			$sql .= ' LIMIT ' . $limit;
		}
		$stmt = $pdo->prepare($sql);
		$stmt->execute(array($topic_id));
		$result =  $stmt->fetchAll();
		foreach($result AS $i => $user) {
			$result[$i]['id'] = intval($user['id']); # convert id to int
			$result[$i]['online'] = intval($user['online']); 
		}
		return $result;
	}
}