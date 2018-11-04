<?php

class API
{
	var $response;
	var $response_data = [];
	var $token;
	var $session;
	var $db;
	var $user_id;
	var $access_level;
	var $last_project_id;
	var $last_segment_id;

	/*-------------------------------------------*/
	/* This section contains the API constructor */
	/*-------------------------------------------*/
	function __construct($db)
	{
		$this->db = $db;
	}

	/*------------------------------------------*/
	/* This section contains the login function */
	/*------------------------------------------*/
	public function login($email, $password)
	{
		// Validate email
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			// Fail
			$this->fail('Invalid email address provided');
		}

		// Check the database and see if there is a user with given email and password
		$result = $this->db->prepare('SELECT * FROM users WHERE email = :email AND password = :password');
		$result->bindParam('email', $email, PDO::PARAM_STR);
		$result->bindParam('password', $password, PDO::PARAM_STR);
		$result->execute();

		// Check if a result was returned
		if($result->rowCount() === 0) {
			// Hard fail
			$this->hard_fail();
		}

		// Get user id
		$row = $result->fetchObject();
		$this->user_id = $row->id;
		$this->access_level = $row->access_level;

		// Generate token and session
		$this->generate_session();
		$this->generate_token();

		// Save session and token in the database
		$update = $this->db->prepare("UPDATE users SET token = :token, session = :session WHERE id = :user_id");
		$update->bindParam('user_id', $this->user_id, PDO::PARAM_INT);
		$update->bindParam('session', $this->session, PDO::PARAM_STR);
		$update->bindParam('token', $this->token, PDO::PARAM_STR);
		$update->execute();	

		// Respond
		$this->respond(['status' => 'ok', 'session' => $this->session, 'token' => $this->token, 'access_level' => $this->access_level]);
	}

	public function validate_credentials($session, $token)
	{
		// Look for session and token in the database
		$result = $this->db->prepare('SELECT * FROM users WHERE session = :session AND token = :token');
		$result->bindParam('session', $session, PDO::PARAM_STR);
		$result->bindParam('token', $token, PDO::PARAM_STR);
		$result->execute();

		// Check if a result was returned
		if($result->rowCount() === 0) {
			// Hard fail
			$this->hard_fail();
		}

		// Get results
		$row = $result->fetchObject();

		// Store user id and access level
		$this->user_id = $row->id;
		$this->access_level = $row->access_level;

		// Generate new token
		$this->generate_token();

		// Store the new token in the database
		$update = $this->db->prepare("UPDATE users SET token = :token WHERE id = :user_id");
		$update->bindParam('user_id', $this->user_id, PDO::PARAM_INT);
		$update->bindParam('token', $this->token, PDO::PARAM_STR);
		$update->execute();	
	}

	/*-------------------------------------------------------------*/
	/* This section contains project get, post and patch functions */
	/*-------------------------------------------------------------*/
	public function get_projects()
	{
		$results = $this->db->query("SELECT * FROM projects ORDER by id DESC");
		$results->execute();

		// Check if anything was returned
		if ($results->rowCount() === 0) {
            // Respond
            $this->respond(['status' => 'no_projects',
                           'token' => $this->token]);
		}

		// Fetch the data
		while($row = $results->fetchObject()) {
			array_push($this->response_data, ['id' => $row->id, 'title' => $row->title, 'description' => $row->description, 'added' => $row->added, 'name' => $this->get_username($row->user_id)]);
		}

        // Respond
		$this->respond(['status' => 'ok',
                       'token' => $this->token,
                       'data' => $this->response_data]);
	}

	public function post_project($title, $description, $glossary, $segments)
	{
		// Validate title, description and glossary length
		if(strlen($title) < 3 || strlen($title) > 255 ||
		   strlen($description) < 3 || strlen($description) > 511 ||
		   strlen($glossary) < 4 || strlen($glossary) > 8191) {
			// Fail
			$this->fail();
		}
		
		// Count equalty marks and semicolons
		$eqMarks = substr_count($glossary, '=');
		$semicolons = substr_count($glossary, ';');
		
		// Validate glossary format
		if ($eqMarks !== $semicolons || $eqMarks < 1 || $semicolons < 1) {
            // Fail
            $this->fail('Invalid glossary format');
		}
		
		// Validate title to be alnum (letters and digits only)
		if (!preg_match('/^[A-Za-z0-9 ]+$/', $title)) {
            // Fail
            $this->fail('Title must be alpha numeric');
		}
		
		// Validate description (letters, digits, spaces and dot only)
		if (!preg_match('/^[A-Za-z0-9 .]+$/', $description)) {
            // Fail
            $this->fail('Description can only include letters, digits, spaces and dots');
		}

		// Validate segments
		for ($i = 0; sizeof($segments) < $i; $i++) {
			if (strlen($segments[$i]['text']) > 50000 || strlen($segments[$i]['text']) < 3) {
				// Fail
				$this->fail('Segment size exceeds maximum limit');
			}
		}

		// Convert title to human readable and routing safe format
		$title = trim($title); // Strips white spaces from the beginning of the string and the end, etc.
		$title = preg_replace("/[\s]/", "-", $title); // Replace all spaces with hyphens
		$title = strtolower($title); // Make all characters lowercase

		// Create the project
		$insert = $this->db->prepare("INSERT INTO projects (id, user_id, title, description, glossary)
													VALUES (NULL, :user_id, :title, :description, :glossary)");
		$insert->bindParam('user_id', $this->user_id, PDO::PARAM_INT);
		$insert->bindParam('title', $title, PDO::PARAM_STR);
		$insert->bindParam('description', $description, PDO::PARAM_STR);
		$insert->bindParam('glossary', $glossary, PDO::PARAM_STR);
		$insert->execute();

		// Store the project id
		$this->last_project_id = $this->db->lastInsertId();

		// Create project source segment/s
		for ($i = 0; $i < sizeof($segments); $i++) {
			// Only variables should be passed by reference fix
			$segment_text = htmlspecialchars($segments[$i]);

			$insert = $this->db->prepare("INSERT INTO source_segments (id, project_id, text) VALUES (NULL, :project_id, :segment_text)");
			$insert->bindParam('project_id', $this->last_project_id, PDO::PARAM_INT);
			$insert->bindParam('segment_text', $segment_text, PDO::PARAM_STR);
			$insert->execute();

			// Store the last segment id
			$this->last_segment_id = $this->db->lastInsertId();
			
			// Generate blank target segment
			$blank_target_segment = str_repeat('0xSep', substr_count($segment_text, '.'));
			
			// Check if there is text after last dot.
			/*if (substr(strrchr($segment_text, "."), 1)) {
                // If there is add another part to segment
                $blank_target_segment .= '0xSep';
			}*/

			// Create project target segment
			$insert = $this->db->prepare("INSERT INTO target_segments (id, project_id, text, complete) VALUES (:last_segment_id, :project_id, :blank_target_segment, '0')");
			$insert->bindParam('last_segment_id', $this->last_segment_id, PDO::PARAM_INT);
			$insert->bindParam('project_id', $this->last_project_id, PDO::PARAM_INT);
			$insert->bindParam('blank_target_segment', $blank_target_segment, PDO::PARAM_STR);
			$insert->execute();
		}

		// Respond
		$this->respond(['status' => 'ok',
                       'token' => $this->token,
                       'project_id' => $this->last_project_id]);
	}
	
	public function delete_project($project_id)
	{
        // Validate project id
        $this->validate_digit($project_id);
        
        // Check access level
        if ($this->access_level !== '2') {
            // Fail
            $this->fail('Insufficient access rights');
        }
        
        // Delete the project
        $delete = $this->db->prepare("DELETE FROM projects WHERE id = :project_id");
        $delete->bindParam('project_id', $project_id, PDO::PARAM_INT);
        $delete->execute();
        
        // Delete source segments
        $delete = $this->db->prepare("DELETE FROM source_segments WHERE project_id = :project_id");
        $delete->bindParam('project_id', $project_id, PDO::PARAM_INT);
        $delete->execute();
        
        // Respond
        $this->respond(['status' => 'ok', 'token' => $this->token]);
	}

	public function get_segments($project_id)
	{
		// Validate project id
		$this->validate_digit($project_id);

		// Get source segments data
		$results = $this->db->prepare("SELECT * FROM source_segments WHERE project_id = :project_id");
		$results->bindParam('project_id', $project_id, PDO::PARAM_INT);
		$results->execute();

		// Make a segments variable
		$segments = [];

		// Process results
		while ($row = $results->fetchObject()) {
			// Get target segment completion state
			$result = $this->db->prepare("SELECT complete, user_id FROM target_segments WHERE id = :segment_id");
			$result->bindParam('segment_id', $row->id, PDO::PARAM_INT);
			$result->execute();

			// Get segment id, completion state
			$row_target_segment = $result->fetchObject();
			$user_id = $row_target_segment->user_id;
			$complete = $row_target_segment->complete;

			// Check if a user has assigned themselves this project
			// Admins can unassign anyone and edit any target segment
			// regardless of who has or hasn't assigned themselves to it
			if ($user_id !== '0') {
				if($user_id === $this->user_id && $this->access_level === '2') {
					$can_unassign = true;
				} else {
					$can_unassign = false;
				}

				// Pack response
				array_push($segments, ['id' => $row->id, 'text' => mb_substr($row->text, 0, 250,'UTF-8'), 'complete' => $complete, 'assigned' => true, 'can_unassign' => $can_unassign, 'can_edit' => $can_unassign]);
			} else {
				// Check if user is an admin
				// Admins can edit unassigned segments
				if ($this->access_level === '2') {
					$can_edit = true;
				} else {
					$can_edit = false;
				}

				// Pack response
				array_push($segments, ['id' => $row->id, 'text' => mb_substr($row->text, 0, 250,'UTF-8'), 'complete' => $complete, 'assigned' => false, 'can_edit' => $can_edit]);
			}
		}

		// Respond
		$this->respond(['status' => 'ok', 'token' => $this->token, 'segments' => $segments]);
	}

	public function get_source_segment($segment_id)
	{
		// Validate input
		$this->validate_digit($segment_id);

		// Get the source segment data from database
		$result = $this->db->prepare("SELECT text FROM source_segments WHERE id = :segment_id");
		$result->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
		$result->execute();

		// Check if anything was retrieved
		if($result->rowCount() !== 1) {
			// Fail
			$this->fail();
		}

		// Fetch data
		$row = $result->fetchObject();

		// Respond
		$this->respond(['status' => 'ok', 'token' => $this->token, 'text' => $row->text]);
	}

	public function get_target_segment($segment_id)
	{
		// Validate input
		$this->validate_digit($segment_id);

		// Get the source segment
		$result = $this->db->prepare("SELECT * FROM target_segments WHERE id = :segment_id");
		$result->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
		$result->execute();

		// Check if anything was retrieved
		if($result->rowCount() < 1) {
			// Fail
			$this->fail();
		}

		// Fetch data
		$row = $result->fetchObject();

		// Check if the user is admin or has this segment assigned to themselves
		if ($this->user_id !== $row->user_id && $this->access_level !== '2') {
			// Fail
			$this->fail('Insufficient access rights');
		}

		// Check if text is null
		if($row->text === null) {
			$text = '';
		} else {
			$text = $row->text;
		}

		// Respond
		$this->respond(['status' => 'ok', 'token' => $this->token, 'text' => $text, 'complete' => $row->complete]);
	}

	public function patch_target_segment($segment_id, $text = null, $complete = null)
	{
		// Validate segment id
		$this->validate_digit($segment_id);

		// Validate complete
		if ($complete !== null && $text === null) {
            $this->validate_digit($complete);
		}

		// Get segment assigned user id and old text for verification
		$result = $this->db->prepare("SELECT user_id, text FROM target_segments where id = :segment_id");
		$result->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
		$result->execute();

		// Check if anything was returned
		if ($result->rowCount() === 0) {
			// Fail
			$this->fail('Could not find target segment');
		}

		// Fetch data
		$row = $result->fetchObject();
		$user_id = $row->user_id;
		$old_text = $row->text;
		
		// Validate text if complete is not being patched
		if ($text !== null && $complete === null) {
            // Make sure the number of parts matches the number of
            // parts in the previous version of target segment text
            $parts_old_text = substr_count($old_text, '0xSep');
            $parts_new_text = substr_count($text, '0xSep');
            
            if ($parts_old_text !== $parts_new_text) {
                // Fail
                $this->fail('Target segment data is corrupted');
            }
		}

		// Check if segment is assigned to this user or user is admin
		if ($this->user_id !== $user_id && $this->access_level !== '2') {
			// Fail
			$this->fail('Insufficient access rights');
		}

		// Check what is being patched - text or completion status
		if ($complete !== null && $text === null) {
			$update = $this->db->prepare("UPDATE target_segments SET complete = :complete WHERE id = :segment_id");
			$update->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
			$update->bindParam('complete', $complete, PDO::PARAM_INT);
			$update->execute();
		} else if ($complete === null && $text !== null) {
			$update = $this->db->prepare("UPDATE target_segments SET text = :text WHERE id = :segment_id");
			$update->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
			$update->bindParam('text', $text, PDO::PARAM_STR);
			$update->execute();
		} else {
			// Fail
			$this->fail('Invalid API request');
		}

		// Respond
		$this->respond(['status' => 'ok', 'token' => $this->token]);
	}
	
		public function assign_segment($segment_id)
	{
		// Validate input
		$this->validate_digit($segment_id);

		// Check if there is already a user assigned to this segment
		$result = $this->db->prepare("SELECT user_id FROM target_segments WHERE id = :segment_id");
		$result->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
		$result->execute();

		if($row = $result->fetchObject()) {
			if ($row->user_id !== '0') {
				// Fail
				$this->fail();
			}
		}

		// Update the assigned user id
		$update = $this->db->prepare("UPDATE target_segments SET user_id = :user_id WHERE id = :segment_id");
		$update->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
		$update->bindParam('user_id', $this->user_id, PDO::PARAM_INT);
		$update->execute();

		// Respond
		$this->respond(['status' => 'ok', 'token' => $this->token, 'segment_id' => $segment_id]);
	}

	public function unassign_segment($segment_id)
	{
		// Validate input
		$this->validate_digit($segment_id);

		// Check if current user can unassign themselves from this segment
		$result = $this->db->prepare("SELECT user_id FROM target_segments WHERE id = :segment_id");
		$result->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
		$result->execute();

		if($row = $result->fetchObject()) {
			if ($row->user_id !== $this->user_id && $this->access_level !== '2') {
				// Fail
				$this->fail();
			}
		}

		// Unassign user from this segment
		$update = $this->db->prepare("UPDATE target_segments SET user_id = 0 WHERE id = :segment_id");
		$update->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
		$update->execute();

		// Respond
		$this->respond(['status' => 'ok', 'token' => $this->token, 'segment_id' => $segment_id]);
	}

	public function get_glossary($project_id)
	{
		// Validate input
		if (!ctype_digit($project_id)) {
			// Fail
			$this->fail();
		}

		$result = $this->db->prepare("SELECT glossary FROM projects WHERE id = :project_id");
		$result->bindParam('project_id', $project_id, PDO::PARAM_INT);
		$result->execute();

		// Check if data was returned
		if ($result->rowCount() === 0) {
			$this->fail();
		}

		// Fetch glossary data
		$row = $result->fetchObject();

		// Respond
		$this->respond(['status' => 'ok', 'token' => $this->token, 'glossary' => $row->glossary]);
	}
	
	public function post_request($text, $context, $project_id, $segment_id)
	{
        // Validate request, letters, digits, dot. comma, dash-, underscore_, etc symbols accepted
        if (!preg_match('/^[A-Za-z0-9\s.,:;!@#$%^&*\(\)\[\]_-]{2,1023}$/', $text)) {
            // Fail
            $this->fail('Invalid request text format');
        }
        
        // Validate request context
        if (!preg_match('/^[A-Za-z0-9\s.,:;!@#$%^&*\(\)\[\]_-]{3,4095}$/', $text)) {
            // Fail
            $this->fail('Invalid request context format');
        }
        
        // Validate project and segment id
        $this->validate_digit($project_id);
        $this->validate_digit($segment_id);
        
        // Insert request into the database
        $insert = $this->db->prepare("INSERT INTO requests (id, user_id, project_id, segment_id, context, text, open, added)
                                                            VALUES(NULL, :user_id, :project_id, :segment_id, :context, :text, 1, CURRENT_TIMESTAMP)");

        $insert->bindParam('user_id', $this->user_id, PDO::PARAM_INT);
        $insert->bindParam('project_id', $project_id, PDO::PARAM_INT);
        $insert->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
        $insert->bindParam('context', $context, PDO::PARAM_STR);
        $insert->bindParam('text', $text, PDO::PARAM_STR);
        $insert->execute();
        
        // Respond
        $this->respond(['status' => 'ok', 'token' => $this->token]);
	}
	
	public function get_requests()
	{
        // Fetch requests from the database
        $results = $this->db->query("SELECT * FROM requests WHERE open = 1 ORDER by id DESC");
        $results->execute();
        
        // Check if there were any requests in the database
		if($results->rowCount() === 0) {
			// Respond
			$this->respond(['status' => 'no_requests', 'token' => $this->token]);
		}
        
        // Pack the response data
		while($row = $results->fetchObject()) {            
            // Check if the request is by the current user
            if ($this->user_id === $row->user_id) {
                // Get answers
                $results_answers = $this->db->prepare("SELECT * FROM answers WHERE request_id = :request_id");
                $results_answers->bindParam('request_id', $row->id, PDO::PARAM_INT);
                $results_answers->execute();
                
                if ($results_answers->rowCount() === 0) {
                    // Insert data into response array
                    array_push($this->response_data, ['name' => $this->get_username($row->user_id),
                                                     'context' => $row->context,
                                                     'text' => $row->text,
                                                     'project_id' => $row->project_id,
                                                     'segment_id' => $row->segment_id,
                                                     'request_id' => $row->id,
                                                     'can_reply' => 0,
                                                     'can_close' => 1]);
                } else {
                    $answers = [];

                    // Fetch the answers
                    while($row_answers = $results_answers->fetchObject()) {
                        array_push($answers, ['text' => $row_answers->text, 'name' => $this->get_username($row_answers->user_id)]);
                    }

                    // Insert data into response array
                    array_push($this->response_data, ['name' => $this->get_username($row->user_id),
                                                     'context' => $row->context,
                                                     'text' => $row->text,
                                                     'project_id' => $row->project_id,
                                                     'segment_id' => $row->segment_id,
                                                     'request_id' => $row->id,
                                                     'can_reply' => 0,
                                                     'can_close' => 1,
                                                     'answers' => $answers]);
                }
			} else {
                // The request does not belong to the current user
                // Check if the current user is admin, admin can close any request
                $can_close = 0;
                if ($this->access_level === '2') {
                    $can_close = 1;
                }

                // Insert data into response array
                array_push($this->response_data, ['name' => $this->get_username($row->user_id),
                                                 'context' => $row->context,
                                                 'text' => $row->text,
                                                 'project_id' => $row->project_id,
                                                 'segment_id' => $row->segment_id,
                                                 'request_id' => $row->id,
                                                 'can_reply' => 1,
                                                 'can_close' => $can_close]);
			}
		}

        // Respond
        $this->respond(['status' => 'ok', 'token' => $this->token, 'requests' => $this->response_data]);
	}
	
	public function patch_request($request_id)
	{
        // Validate request id
        $this->validate_digit($request_id);

        // Check if the request belongs to the current user or if the user is admin
        $result = $this->db->prepare("SELECT user_id FROM requests WHERE id = :request_id");
        $result->bindParam('request_id', $request_id, PDO::PARAM_INT);
        $result->execute();
        
        // Get user id
        $row = $result->fetchObject();
        
        if ($this->user_id !== $row->user_id && $this->access_level !== '2') {
            // Fail
            $this->fail('This request does not belong to you');
        }
        
        // Update the request
        $update = $this->db->prepare("UPDATE requests SET open = 0 WHERE id = :request_id");
        $update->bindParam('request_id', $request_id, PDO::PARAM_INT);
        $update->execute();

        // Respond
        $this->respond(['status' => 'ok', 'token' => $this->token]);
	}
	
	public function post_answer($text, $project_id, $segment_id, $request_id)
	{
        // Validate id's
        $this->validate_digit($project_id);
        $this->validate_digit($segment_id);
        $this->validate_digit($request_id);
        
        // Validate answer text
        if (!preg_match('/^[A-Za-z0-9\s.,:;!@#$%^&*\(\)\[\]_-]{2,1023}$/', $text)) {
            // Fail
            $this->fail('Invalid answer text format');
        }
        
        // Insert answer into the database
        $insert = $this->db->prepare("INSERT INTO answers (id, user_id, project_id, segment_id, request_id, text)
                                                            VALUES(NULL, :user_id, :project_id, :segment_id, :request_id, :text)");

        $insert->bindParam('user_id', $this->user_id, PDO::PARAM_INT);
        $insert->bindParam('project_id', $project_id, PDO::PARAM_INT);
        $insert->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
        $insert->bindParam('request_id', $request_id, PDO::PARAM_INT);
        $insert->bindParam('text', $text, PDO::PARAM_STR);
        $insert->execute();
        
        // Respond
        $this->respond(['status' => 'ok', 'token' => $this->token]);
	}

	/*public function get_segment_translation($segment_id)
	{
        // Validate segment id
        $this->validate_digit($segment_id);
        
        // Check access level
        if ($this->access_level !== '2') {
            // Fail
            $this->fail('Insufficient access rights');
        }
        
        // Get project data
        $results = $this->db->prepare("SELECT text FROM target_segments WHERE id = :project_id");
        $results->bindParam('segment_id', $segment_id, PDO::PARAM_INT);
        $results->execute();
        
        // Get result
        $row = $results->fetchObject();
        
        // Respond
        $this->respond(['status' => 'ok', 'token' => $this->token, 'segment' => $row->text]);
	}*/

	public function get_project_translation($project_id)
	{
        // Validate segment id
        $this->validate_digit($project_id);
        
        // Check access level
        if ($this->access_level !== '2') {
            // Fail
            $this->fail('Insufficient access rights');
        }
        
        // Get project data
        $results = $this->db->prepare("SELECT text FROM target_segments WHERE project_id = :project_id");
        $results->bindParam('project_id', $project_id, PDO::PARAM_INT);
        $results->execute();
        
        // Get results
        while($row = $results->fetchObject()) {
            array_push($this->response_data, $row->text);
        }
        
        // Respond
        $this->respond(['status' => 'ok', 'token' => $this->token, 'segments' => $this->response_data]);
	}

	/*-----------------------------------------------------------------------*/
	/* This section contains repetitively used, API class internal functions */
	/*-----------------------------------------------------------------------*/
	private function generate_token()
	{
	    // Generate a random number and character string
	    $this->token = bin2hex(random_bytes(32));
	}

	private function generate_session()
	{
        // Generate a random number and character string
		$this->session = bin2hex(random_bytes(32));
	}

	private function get_username($user_id)
	{
		// Validate user id
		$this->validate_digit($user_id);

		$result = $this->db->prepare("SELECT name FROM users WHERE id = :user_id");
		$result->bindParam('user_id', $this->user_id, PDO::PARAM_INT);
		$result->execute();

		// Get user data
		$row = $result->fetchObject();

		// Return username
		return $row->name;
	}

	private function validate_digit($value)
	{
        // Validate that the string contains digits
		if (!ctype_digit($value)) {
			// Fail
			$this->fail('Parameter must be a digit');
		}
		
		// Validate the length, which is max 10 digits as limited in the database
		if (preg_match_all("/[0-9]/", $value) > 10) {
            // Fail
            $this->fail('Parameter exceeds the maximum number of digits');
		}
	}

	private function hard_fail()
	{
	    // Respond
	    $this->respond(['status' => 'hard_fail']);
	}

	private function fail($error_message = null)
	{
		// Pack response
		if ($error_message === null) {
            $this->respond(['satus' => 'fail', 'token' => $this->token]);
		} else {
            $this->respond(['satus' => 'fail', 'token' => $this->token, 'error' => $error_message]);
		}
	}

	private function respond($response)
	{
		exit(")]}',\n" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	}
}
?>