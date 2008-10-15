<?php

require_once dirname( __FILE__ ) . '/polldaddy-xml.php';

// TODO: polls->poll should always be an array and similar bad typing
class PollDaddy_Client {
	var $polldaddy_url = 'http://api.polldaddy.com/handler/';
	var $partnerGUID;
	var $userCode;

	var $request = null;
	var $response = null;
	var $request_xml = '';
	var $response_xml = '';

	var $errors = array();

	function PollDaddy_Client( $partnerGUID = '', $userCode = null ) {
		$this->partnerGUID = $partnerGUID;
		$this->userCode = $userCode;
	}

	function send_request() {
		$this->request_xml  = "<?xml version='1.0' encoding='utf-8' ?>\n";
		$this->request_xml .= $this->request->xml( 'all' );

		if ( function_exists( 'wp_remote_post' ) ) {
			$response = wp_remote_post( $this->polldaddy_url, array(
				'headers' => array( 'Content-Type' => 'text/xml; charset=utf-8' ),
				'user-agent' => 'PollDaddy PHP Client/0.1',
				'body' => $this->request_xml
			) );
			if ( !$response || is_wp_error( $response ) ) {
				$errors[-1] = "Can't connect";
				return false;
			}
			$this->response_xml = wp_remote_retrieve_body( $response );
		} else {
			$parsed = parse_url( $this->polldaddy_url );

			$fp = fsockopen(
				$parsed['host'],
				$parsed['scheme'] == 'ssl' || $parsed['scheme'] == 'https' && extension_loaded('openssl') ? 443 : 80,
				$err_num,
				$err_str,
				3
			);

			if ( !$fp ) {
				$errors[-1] = "Can't connect";
				return false;
			}

			if ( function_exists( 'stream_set_timeout' ) )
				stream_set_timeout( $fp, 3 );

			if ( !$path = $parsed['path'] . ( isset($parsed['query']) ? '?' . $parsed['query'] : '' ) )
				$path = '/';

			$request  = "POST $path HTTP/1.0\r\n";
			$request .= "Host: {$parsed['host']}\r\n";
			$request .= "User-agent: PollDaddy PHP Client/0.1\r\n";
			$request .= "Content-Type: text/xml; charset=utf-8\r\n";
			$request .= 'Content-Length: ' . strlen( $this->request_xml ) . "\r\n";

			fwrite( $fp, "$request\r\n$this->request_xml" );

			$response = '';
			while ( !feof( $fp ) )
				$response .= fread( $fp, 4096 );
			fclose( $fp );


			if ( !$response ) {
				$errors[-2] = 'No Data';
			}

			list($headers, $this->response_xml) = explode( "\r\n\r\n", $response, 2 );
		}

		$parser = new PollDaddy_XML_Parser( $this->response_xml );
		$this->response =& $parser->objects[0];
		if ( isset( $this->response->errors->error ) ) {
			if ( !is_array( $this->response->errors->error ) )
				$this->response->errors->error = array( $this->response->errors->error );
			foreach ( $this->response->errors->error as $error )
				$this->errors[$error->_id] = $error->___content;
		}
	}

	function response_part( $pos ) {
		if ( !isset( $this->response->demands->demand ) )
			return false;

		if ( is_array( $this->response->demands->demand ) ) {
			if ( isset( $this->response->demands->demand[$pos] ) )
				return $this->response->demands->demand[$pos];
			return false;
		}

		if ( 0 === $pos )
			return $this->response->demands->demand;

		return false;
	}

	function add_request( $demand, $object = null ) {
		if ( !is_a( $this->request, 'PollDaddy_Request' ) )
			$this->request = new PollDaddy_Request( array(
				'userCode' => $this->userCode,
				'demands' => new PollDaddy_Demands( array( 'demand' => array() ) )
			), array(
				'partnerGUID' => $this->partnerGUID
			) );

		if ( is_a( $object, 'Ghetto_XML_Object' ) )
			$args = array( $object->___name => &$object );
		elseif ( is_array( $object ) )
			$args =& $object;
		else
			$args = null;

		$this->request->demands->demand[] = new PollDaddy_Demand( $args, array( 'id' => $demand ) );

		return count( $this->request->demands->demand ) - 1;
	}

	function reset() {
		$this->request = null;
		$this->response = null;
		$this->request_xml = '';
		$this->response_xml = '';
		$this->errors = array();
	}

/* pdInitiate: Initiate API "connection" */

	/**
	 * @param string $Email
	 * @param string $Password
	 * @param int $partnerUserID
	 * @return string|false PollDaddy userCode or false on failure
	 */
	function Initiate( $Email, $Password, $partnerUserID ) {
		$this->request = new PollDaddy_Initiate( compact( 'Email', 'Password' ), array( 'partnerGUID' => $this->partnerGUID, 'partnerUserID' => $partnerUserID ) );
		$this->send_request();
		if ( isset( $this->response->userCode ) )
			return $this->response->userCode;
		return false;
	}


/* pdAccess: API Access Control */

	/**
	 * @param string $partnerUserID
	 * @return string|false PollDaddy userCode or false on failure
	 */
	function GetUserCode( $partnerUserID ) {
		$this->request = new PollDaddy_Access( array(
//			'demands' => new PollDaddy_Demands( array( 'demand' => new PollDaddy_Demand( null, array( 'id' => __FUNCTION__ ) ) ) )
			'demands' => new PollDaddy_Demands( array( 'demand' => new PollDaddy_Demand( null, array( 'id' => 'GetUserCode' ) ) ) )
		), array(
			'partnerGUID' => $this->partnerGUID,
			'partnerUserID' => $partnerUserID
		) );
		$this->send_request();
		if ( isset( $this->response->userCode ) )
			return $this->response->userCode;
		return false;
	}

	// Not Implemented
	function RemoveUserCode() {
		return false;
	}

	/**
	 * @see polldaddy_account()
	 * @param int $partnerUserID
	 * @param array $args polldaddy_account() args
	 * @return string|false PollDaddy userCode or false on failure
	 */
	function CreateAccount( $partnerUserID, $args ) {
		if ( !$account = polldaddy_account( $args ) )
			return false;

		$this->request = new PollDaddy_Access( array(
//			'demands' => new PollDaddy_Demands( array( 'demand' => new PollDaddy_Demand( compact( 'account' ), array( 'id' => __FUNCTION__ ) ) ) )
			'demands' => new PollDaddy_Demands( array( 'demand' => new PollDaddy_Demand( compact( 'account' ), array( 'id' => 'CreateAccount' ) ) ) )
		), array(
			'partnerGUID' => $this->partnerGUID,
			'partnerUserID' => $partnerUserID
		) );
		$this->send_request();
		if ( isset( $this->response->userCode ) )
			return $this->response->userCode;
		return false;
	}


/* pdRequest: Request API Objects */

  /* Accounts */
	/**
	 * @return object|false PollDaddy Account or false on failure
	 */
	function GetAccount() {
//		$pos = $this->add_request( __FUNCTION__ );
		$pos = $this->add_request( 'GetAccount' );
		$this->send_request();
		$r = $this->response_part( $pos );
		if ( isset( $r->account ) && !is_null( $r->account->email ) )
			return $r->account;
		return false;
	}

	/**
	 * @see polldaddy_account()
	 * @param array $args polldaddy_account() args
	 * @return string|false PollDaddy userCode or false on failure
	 */
	function UpdateAccount( $args ) {
		if ( !$account = polldaddy_account( $args ) )
			return false;

//		$this->add_request( __FUNCTION__, $account );
		$this->add_request( 'UpdateAccount', $account );
		$this->send_request();
		if ( isset( $this->response->userCode ) )
			return $this->response->userCode;
		return false;
	}

  /* Polls */
	/**
	 * @return array|false Array of PollDaddy Polls or false on failure
	 */
	function ListPolls( $start = 0, $end = 0 ) {
		$start = (int) $start;
		$end = (int) $end;
		if ( !$start && !$end )
//			$pos = $this->add_request( __FUNCTION__ );
			$pos = $this->add_request( 'ListPolls' );
		else
//			$pos = $this->add_request( __FUNCTION__, new PollDaddy_List( null, compact( 'start', 'end' ) ) );
			$pos = $this->add_request( 'ListPolls', new PollDaddy_List( null, compact( 'start', 'end' ) ) );
		$this->send_request();
		$r = $this->response_part( $pos );
		if ( isset( $r->polls ) ) {
			if ( isset( $r->polls->poll ) ) {
				if ( !is_array( $r->polls->poll ) )
					$r->polls->poll = array( $r->polls->poll );
			}
			return $r->polls;
		}
		return false;
	}

	/**
	 * @return array|false Array of PollDaddy Polls or false on failure
	 */
	function listPollsByBlog( $start = 0, $end = 0, $id = null ) {
		$start = (int) $start;
		$end = (int) $end;
		if ( !is_numeric( $id ) )
			$id = $GLOBALS['blog_id'];

		if ( !$start && !$end )
//			$pos = $this->add_request( __FUNCTION__ );
			$pos = $this->add_request( 'listPollsByBlog', compact( 'id' ) );
		else
//			$pos = $this->add_request( __FUNCTION__, new PollDaddy_List( null, compact( 'start', 'end' ) ) );
			$pos = $this->add_request( 'listPollsByBlog', new PollDaddy_List( null, compact( 'start', 'end', 'id' ) ) );
		$this->send_request();
		$r = $this->response_part( $pos );
		if ( isset( $r->polls ) ) {
			if ( isset( $r->polls->poll ) ) {
				if ( !is_array( $r->polls->poll ) )
					$r->polls->poll = array( $r->polls->poll );
			}
			return $r->polls;
		}
		return false;
	}

	/**
	 * @param int $id PollDaddy Poll ID
	 * @return array|false PollDaddy Poll or false on failure
	 */
	function GetPoll( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new PollDaddy_Poll( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'GetPoll', new PollDaddy_Poll( null, compact( 'id' ) ) );
		$this->send_request();

		$demand = $this->response_part( $pos );
		if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->poll ) && !is_null( $demand->poll->question ) ) {
			if ( isset( $demand->poll->answers->answer ) && !is_array( $demand->poll->answers->answer ) ) {
				if ( $demand->poll->answers->answer )
					$demand->poll->answers->answer = array( $demand->poll->answers->answer );
				else
					$demand->poll->answers->answer = array();
			}
			return $demand->poll;
		}
		return false;
	}

	/**
	 * @param int $id PollDaddy Poll ID
	 * @return bool success
	 */
	function DeletePoll( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new PollDaddy_Poll( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'DeletePoll', new PollDaddy_Poll( null, compact( 'id' ) ) );
		$this->send_request();

		return empty( $this->errors );
	}

	/**
	 * @see polldaddy_poll()
	 * @param array $args polldaddy_poll() args
	 * @return array|false PollDaddy Poll or false on failure
	 */
	function CreatePoll( $args = null ) {
		if ( !$poll = polldaddy_poll( $args ) )
			return false;
//		$pos = $this->add_request( __FUNCTION__, $poll );
		$pos = $this->add_request( 'CreatePoll', $poll );
		$this->send_request();
		if ( !$demand = $this->response_part( $pos ) )
			return $demand;
		if ( !isset( $demand->poll ) )
			return false;
		return $demand->poll;

	}

	/**
	 * @see polldaddy_poll()
	 * @param int $id PollDaddy Poll ID
	 * @param array $args polldaddy_poll() args
	 * @return array|false PollDaddy Poll or false on failure
	 */
	function UpdatePoll( $id, $args = null ) {
		if ( !$id = (int) $id )
			return false;

		if ( !$poll = polldaddy_poll( $args, $id ) )
			return false;

//		$pos = $this->add_request( __FUNCTION__, $poll );
		$pos = $this->add_request( 'UpdatePoll', $poll );
		$this->send_request();
		return $this->response_part( $pos );
	}

	function SearchPolls( $search ) {
//		$pos = $this->add_request( __FUNCTION__, compact( 'search' ) );
		$pos = $this->add_request( 'SearchPolls', compact( 'search' ) );
		$this->send_request();

		$r = $this->response_part( $pos );
		if ( isset( $r->search ) ) {
			if ( isset( $r->search->poll ) ) {
				if ( is_array( $r->search->poll ) )
					return $r->search->poll;
				else
					return array( $r->search->poll );
			}
			return array();
		}
		return false;
	}

	// Not Implemented
	function ListSurveys() {
		return false;
	}

  /* Poll Results */
	/**
	 * @param int $id PollDaddy Poll ID
	 * @return object|false PollDaddy Result or false on failure
	 */
	function GetPollResults( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new PollDaddy_Poll_Result( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'GetPollResults', new PollDaddy_Poll_Result( null, compact( 'id' ) ) );
		$this->send_request();

		$demand = $this->response_part( $pos );
		
		if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->result ) ) {
			$answers = $others = array();
			if ( isset( $demand->result->answers ) ) {
				if ( isset( $demand->result->answers->answer ) ) {
					if ( is_array( $demand->result->answers->answer ) )
						$answers = $demand->result->answers->answer;
					else
						$answers = array( $demand->result->answers->answer );
				}
			}
			if ( isset( $demand->result->otherAnswers ) ) {
				if ( isset( $demand->result->otherAnswers->otherAnswer ) ) {
					if ( is_array( $demand->result->otherAnswers->otherAnswer ) )
						$others = $demand->result->otherAnswers->otherAnswer;
					else
						$others = array( $demand->result->otherAnswers->othernswer );
				}
			}
			return (object) compact( 'answers', 'others' );
		}
		return false;
	}

	/**
	 * @param int $id PollDaddy Poll ID
	 * @return object|false PollDaddy Result or false on failure
	 */
	function ResetPollResults( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new PollDaddy_Poll_Result( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'ResetPollResults', new PollDaddy_Poll_Result( null, compact( 'id' ) ) );
		$this->send_request();

		return empty( $this->errors );
	}

  /* Poll Comments */
	// Not Implemented
	function GetPollComments() {
		return false;
	}

	// Not Implemented
	function ModerateComment() {
		return false;
	}

  /* Extensions */

	// Not Implemented
	function GetAllExtensions() {
		return false;
	}

	// Not Implemented
	function GetLanguages() {
		return false;
	}

   /* Language Packs */
	// Not Implemented
	function GetPacks() {
		return false;
	}

	// Not Implemented
	function GetPack() {
		return false;
	}

	// Not Implemented
	function DeletePack() {
		return false;
	}

	// Not Implemented
	function CreatePack() {
		return false;
	}

	// Not Implemented
	function UpdatePack() {
		return false;
	}

   /* Styles */
	// Not Implemented
	function GetStyles() {
		return false;
	}

	// Not Implemented
	function GetStyle() {
		return false;
	}

	// Not Implemented
	function DeleteStyle() {
		return false;
	}

	// Not Implemented
	function CreateStyle() {
		return false;
	}

	// Not Implemented
	function UpdateStyle() {
		return false;
	}

  /* Folders */
	// Not Implemented
	function GetFolders() {
		return false;
	}

  /* Account Activities */
	// Not Implemented
	function GetActivity() {
		return false;
	}

	// Not Implemented
	function SetActivity() {
		return false;
	}
}

/**
 * @param string $partnerUserID
 * @param string $userName
 * @param string $email
 * @param string $password
 * @param string $firstName
 * @param string $lastName
 * @param string $countryCode
 * @param string $gender
 * @param string $yearOfBirth
 * @param string $websiteURL
 * @param string $avatarURL
 * @param string $bio
 */
function &polldaddy_account( $args = null ) {
	$false = false;
	if ( is_a( $args, 'PollDaddy_Account' ) )
		return $args;

	$defaults = _polldaddy_account_defaults();

	$args = wp_parse_args( $args, $defaults );

	foreach ( array( 'userName', 'email' ) as $required )
		if ( !is_string( $args[$required] ) || !$args[$required] )
			return $false;

	return new PollDaddy_Account( $args );
}

function _polldaddy_account_defaults() {
	return array(
		'userName' => false,
		'email' => false,
		'password' => false,
		'firstName' => false,
		'lastName' => false,
		'countryCode' => 'nn',
		'gender' => 'male',
		'yearOfBirth' => 1901,
		'websiteURL' => false,
		'avatarURL' => false,
		'bio' => false
	);
}

function &polldaddy_poll( $args = null, $id = null, $_require_data = true ) {
	$false = false;
	if ( is_a( $args, 'PollDaddy_Poll' ) ) {
		if ( is_null( $id ) )
			return $args;
		if ( !$id = (int) $id )
			return $false;
		$args->_id = $id;
		return $args;
	}

	$defaults = _polldaddy_poll_defaults();

	if ( !is_null( $args ) ) {
		$args = wp_parse_args( $args, $defaults );
		$args['parentID'] = (int) $args['parentID'];

		if ( $_require_data ) {
			if ( !is_string( $args['question'] ) || !$args['question'] )
				return $false;

			if ( !is_array($args['answers']) || !$args['answers'] )
				return $false;
		}

		foreach ( array( 'multipleChoice', 'randomiseAnswers', 'otherAnswer', 'makePublic', 'closePoll', 'closePollNow' ) as $bool ) {
			if ( 'no' !== $args[$bool] && 'yes' !== $args[$bool] )
				$args[$bool] = $defaults[$bool];
		}

		foreach ( array( 'styleID', 'packID', 'folderID', 'languageID' ) as $int )
			if ( !is_numeric( $args[$int] ) )
				$args[$bool] = $defaults[$int];

		if ( !in_array( $args['resultsType'], array( 'show', 'percent', 'hide' ) ) )
			$args['resultsType'] = $defaults['resultsType'];

		if ( !in_array( $args['blockRepeatVotersType'], array( 'off', 'cookie', 'cookieIP' ) ) )
			$args['blockRepeatVotersType'] = $defaults['blockRepeatVotersType'];

		if ( !in_array( $args['comments'], array( 'off', 'allow', 'moderate' ) ) )
			$args['comments'] = $defaults['comments'];

		if ( is_numeric( $args['closeDate'] ) )
			$args['closeDate'] = gmdate( 'Y-m-d\TH:i:s', $args['closeDate'] ) . 'Z';
		if ( !$args['closeDate'] )
			$args['closeDate'] = gmdate( 'Y-m-d\TH:i:s' ) . 'Z';

		$args['answers'] = new PollDaddy_Poll_Answers( array( 'answer' => $args['answers'] ) );

		if ( is_null( $id ) )
			$id = $args['id'];
		unset( $args['id'] );
	}

	return new PollDaddy_Poll( $args, compact( 'id' ) );
}

function _polldaddy_poll_defaults() {
	return array(
		'id' => null,
		'question' => false,
		'multipleChoice' => 'no',
		'randomiseAnswers' => 'no',
		'otherAnswer' => 'no',
		'resultsType' => 'show',
		'blockRepeatVotersType' => 'cookie',
		'comments' => 'off',
		'makePublic' => 'yes',
		'closePoll' => 'no',
		'closePollNow' => 'no',
		'closeDate' => null,
		'styleID' => 0,
		'packID' => 0,
		'folderID' => 0,
		'languageID' => _polldaddy_poll_default_language_id(),
		'parentID' => (int) $GLOBALS['blog_id'],
		'answers' => array()
	);
}

if ( !function_exists( '_polldaddy_poll_default_language_id' ) ) :
function _polldaddy_poll_default_language_id() {
	return 1;
}
endif;

function &polldaddy_poll_answer( $answer, $id = null ) {
	if ( !is_string( $answer ) || !$answer )
		return false;

	return new PollDaddy_Poll_Answer( $answer, compact( 'id' ) );
}

if ( !function_exists( 'wp_parse_args' ) ) :
/**
 * Merge user defined arguments into defaults array.
 *
 * This function is used throughout WordPress to allow for both string or array
 * to be merged into another array.
 *
 * @since 2.2.0
 *
 * @param string|array $args Value to merge with $defaults
 * @param array $defaults Array that serves as the defaults.
 * @return array Merged user defined values with defaults.
 */
function wp_parse_args( $args, $defaults = '' ) {
	if ( is_object( $args ) )
		$r = get_object_vars( $args );
	elseif ( is_array( $args ) )
		$r =& $args;
	else
		wp_parse_str( $args, $r );

	if ( is_array( $defaults ) )
		return array_merge( $defaults, $r );
	return $r;
}
endif;

if ( !function_exists( 'wp_parse_str' ) ) :
/**
 * Parses a string into variables to be stored in an array.
 *
 * Uses {@link http://www.php.net/parse_str parse_str()} and stripslashes if
 * {@link http://www.php.net/magic_quotes magic_quotes_gpc} is on.
 *
 * @since 2.2.1
 * @uses apply_filters() for the 'wp_parse_str' filter.
 *
 * @param string $string The string to be parsed.
 * @param array $array Variables will be stored in this array.
 */
function wp_parse_str( $string, &$array ) {
	parse_str( $string, $array );
	if ( get_magic_quotes_gpc() )
		$array = stripslashes_deep( $array );
	return $array;

	$array = apply_filters( 'wp_parse_str', $array );
}
endif;

if ( !function_exists( 'stripslashes_deep' ) ) :
/**
 * Navigates through an array and removes slashes from the values.
 *
 * If an array is passed, the array_map() function causes a callback to pass the
 * value back to the function. The slashes from this value will removed.
 *
 * @since 2.0.0
 *
 * @param array|string $value The array or string to be striped.
 * @return array|string Stripped array (or string in the callback).
 */
function stripslashes_deep($value) {
	$value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
	return $value;
}
endif;
