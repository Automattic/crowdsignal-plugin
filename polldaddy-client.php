<?php

require_once dirname( __FILE__ ) . '/polldaddy-xml.php';

class api_client {
	var $polldaddy_url = 'http://api.polldaddy.com/';
	var $partnerGUID;
	var $userCode;
	var $admin        = 0;
	var $version      = '1.0';
	var $request      = null;
	var $response     = null;
	var $request_xml  = '';
	var $response_xml = '';
	var $requests     = array();
	var $responses    = array();
	var $errors       = array();

	function api_client( $partnerGUID = '', $userCode = null ) {
		$this->partnerGUID = $partnerGUID;
		$this->userCode = $userCode;
	}

	function send_request( $timeout = 3 ) {
		$this->request_xml  = "<?xml version='1.0' encoding='utf-8' ?>\n";
		$this->request_xml .= $this->request->xml( 'all' );
		
		$this->requests[] = $this->request_xml;

		if ( function_exists( 'wp_remote_post' ) ) {
			$response = wp_remote_post( $this->polldaddy_url, array(
				'headers' => array( 'Content-Type' => 'text/xml; charset=utf-8', 'Content-Length' => strlen( $this->request_xml ) ),
				'user-agent' => 'Polldaddy PHP Client/0.1',
				'timeout' => $timeout,
				'body' => $this->request_xml
			) );
			if ( !$response || is_wp_error( $response ) ) {
				$errors[-1] = "Can't connect";
				return false;
			}
			$this->response_xml = wp_remote_retrieve_body( $response );
		} else {
			$parsed = parse_url( $this->polldaddy_url );

			if ( !isset( $parsed['host'] ) && !isset( $parsed['scheme'] ) ) {
				$errors[-1] = 'Invalid API URL';
				return false;
			}
			
			$fp = fsockopen(
				$parsed['host'],
				$parsed['scheme'] == 'ssl' || $parsed['scheme'] == 'https' && extension_loaded('openssl') ? 443 : 80,
				$err_num,
				$err_str,
				$timeout
			);

			if ( !$fp ) {
				$errors[-1] = "Can't connect";
				return false;
			}

			if ( function_exists( 'stream_set_timeout' ) )
				stream_set_timeout( $fp, $timeout );

			if ( !isset( $parsed['path']) || !$path = $parsed['path'] . ( isset($parsed['query']) ? '?' . $parsed['query'] : '' ) )
				$path = '/';

			$request  = "POST $path HTTP/1.0\r\n";
			$request .= "Host: {$parsed['host']}\r\n";
			$request .= "User-agent: Polldaddy PHP Client/0.1\r\n";
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
		
		$this->responses[] = $this->response_xml;

		$parser = new Polldaddy_XML_Parser( $this->response_xml );
		
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
		if ( !is_a( $this->request, 'Polldaddy_Request' ) )
			$this->request = new Polldaddy_Request( array(
				'userCode' => $this->userCode,
				'demands' => new Polldaddy_Demands( array( 'demand' => array() ) )
			), array(
				'version' => $this->version,
				'admin' => $this->admin,
				'partnerGUID' => $this->partnerGUID
			) );

		if ( is_a( $object, 'Ghetto_XML_Object' ) )
			$args = array( $object->___name => &$object );
		elseif ( is_array( $object ) )
			$args =& $object;
		else
			$args = null;

		$this->request->demands->demand[] = new Polldaddy_Demand( $args, array( 'id' => $demand ) );

		return count( $this->request->demands->demand ) - 1;
	}

	function reset() {
		$this->request       = null;
		$this->response      = null;
		$this->request_data  = '';
		$this->response_data = '';
		$this->request_xml   = '';
		$this->response_xml  = '';
		$this->request_json  = '';
		$this->response_json = '';
		$this->errors        = array();
	}

/* pdInitiate: Initiate API "connection" */

	/**
	 * @param string $Email
	 * @param string $Password
	 * @param int $partnerUserID
	 * @return string|false Polldaddy userCode or false on failure
	 */
	function initiate( $Email, $Password, $partnerUserID ) {
		$this->request = new Polldaddy_Initiate( compact( 'Email', 'Password' ), array( 'partnerGUID' => $this->partnerGUID, 'partnerUserID' => $partnerUserID ) );
		$this->send_request();
		if ( isset( $this->response->userCode ) )
			return $this->response->userCode;
		return false;
	}


/* pdAccess: API Access Control */

	/**
	 * @param string $partnerUserID
	 * @return string|false Polldaddy userCode or false on failure
	 */
	function get_usercode( $partnerUserID ) {
		$this->request = new Polldaddy_Access( array(
//			'demands' => new Polldaddy_Demands( array( 'demand' => new Polldaddy_Demand( null, array( 'id' => __FUNCTION__ ) ) ) )
			'demands' => new Polldaddy_Demands( array( 'demand' => new Polldaddy_Demand( null, array( 'id' => 'getusercode' ) ) ) )
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
	function remove_usercode() {
		return false;
	}

	/**
	 * @see polldaddy_account()
	 * @param int $partnerUserID
	 * @param array $args polldaddy_account() args
	 * @return string|false Polldaddy userCode or false on failure
	 */
	function create_account( $partnerUserID, $args ) {
		if ( !$account = polldaddy_account( $args ) )
			return false;

		$this->request = new Polldaddy_Access( array(
//			'demands' => new Polldaddy_Demands( array( 'demand' => new Polldaddy_Demand( compact( 'account' ), array( 'id' => __FUNCTION__ ) ) ) )
			'demands' => new Polldaddy_Demands( array( 'demand' => new Polldaddy_Demand( compact( 'account' ), array( 'id' => 'createaccount' ) ) ) )
		), array(
			'partnerGUID' => $this->partnerGUID,
			'partnerUserID' => $partnerUserID
		) );
		$this->send_request();
		if ( isset( $this->response->userCode ) )
			return $this->response->userCode;
		return false;
	}

function sync_rating( ){
          $pos = $this->add_request( 'syncrating', new Polldaddy_Rating( null , null ) );
  
          $this->send_request();
  
          $demand = $this->response_part( $pos );
  
          if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->rating ) ){
                  return $demand->rating;
          }
  
          return false;
  
  }

/* pdRequest: Request API Objects */

  /* Accounts */
	/**
	 * @return object|false Polldaddy Account or false on failure
	 */
	function get_account() {
//		$pos = $this->add_request( __FUNCTION__ );
		$pos = $this->add_request( 'getaccount' );
		$this->send_request();
		$r = $this->response_part( $pos );
		if ( isset( $r->account ) && !is_null( $r->account->email ) )
			return $r->account;
		return false;
	}

	/**
	 * @see polldaddy_account()
	 * @param array $args polldaddy_account() args
	 * @return string|false Polldaddy userCode or false on failure
	 */
	function update_account( $args ) {
		if ( !$account = polldaddy_account( $args ) )
			return false;

//		$this->add_request( __FUNCTION__, $account );
		$this->add_request( 'updateaccount', $account );
		$this->send_request();
		if ( isset( $this->response->userCode ) )
			return $this->response->userCode;
		return false;
	}

  /* Polls */
	/**
	 * @return array|false Array of Polldaddy Polls or false on failure
	 */
	function get_polls( $start = 0, $end = 0 ) {
		$start = (int) $start;
		$end = (int) $end;
		if ( !$start && !$end )
//			$pos = $this->add_request( __FUNCTION__ );
			$pos = $this->add_request( 'getpolls' );
		else
//			$pos = $this->add_request( __FUNCTION__, new Polldaddy_List( null, compact( 'start', 'end' ) ) );
			$pos = $this->add_request( 'getpolls', new Polldaddy_List( null, compact( 'start', 'end' ) ) );
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
	 * @return array|false Array of Polldaddy Polls or false on failure
	 */
	function get_polls_by_parent_id( $start = 0, $end = 0, $id = null ) {
		$start = (int) $start;
		$end = (int) $end;
		if ( !is_numeric( $id ) )
			$id = $GLOBALS['blog_id'];

		if ( !$start && !$end )
//			$pos = $this->add_request( __FUNCTION__ );
			$pos = $this->add_request( 'getpolls', compact( 'id' ) );
		else
//			$pos = $this->add_request( __FUNCTION__, new Polldaddy_List( null, compact( 'start', 'end', 'id' ) ) );
			$pos = $this->add_request( 'getpolls', new Polldaddy_List( null, compact( 'start', 'end', 'id' ) ) );
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
	 * @param int $id Polldaddy Poll ID
	 * @return array|false Polldaddy Poll or false on failure
	 */
	function get_poll( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Poll( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'getpoll', new Polldaddy_Poll( null, compact( 'id' ) ) );
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
	 * @param int $id Polldaddy Poll ID
	 * @return array|false Polldaddy Poll or false on failure
	 */
	function build_poll( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Poll( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'buildpoll', new Polldaddy_Poll( null, compact( 'id' ) ) );
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
	 * @param int $id Polldaddy Poll ID
	 * @return bool success
	 */
	function delete_poll( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Poll( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'deletepoll', new Polldaddy_Poll( null, compact( 'id' ) ) );
		$this->send_request();

		return empty( $this->errors );
	}

	/**
	 * @param int $id Polldaddy Poll ID
	 * @return bool success
	 */
	function open_poll( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Poll( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'openpoll', new Polldaddy_Poll( null, compact( 'id' ) ) );
		$this->send_request();

		return empty( $this->errors );
	}
	
	/**
	 * @param int $id Polldaddy Poll ID
	 * @return bool success
	 */
	function close_poll( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Poll( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'closepoll', new Polldaddy_Poll( null, compact( 'id' ) ) );
		$this->send_request();

		return empty( $this->errors );
	}

	/**
	 * @see polldaddy_poll()
	 * @param array $args polldaddy_poll() args
	 * @return array|false Polldaddy Poll or false on failure
	 */
	function create_poll( $args = null ) {
		if ( !$poll = polldaddy_poll( $args ) )
			return false;
//		$pos = $this->add_request( __FUNCTION__, $poll );
		$pos = $this->add_request( 'createpoll', $poll );
		$this->send_request();
		if ( !$demand = $this->response_part( $pos ) )
			return $demand;
		if ( !isset( $demand->poll ) )
			return false;
		return $demand->poll;

	}

	/**
	 * @see polldaddy_poll()
	 * @param int $id Polldaddy Poll ID
	 * @param array $args polldaddy_poll() args
	 * @return array|false Polldaddy Poll or false on failure
	 */
	function update_poll( $id, $args = null ) {
		if ( !$id = (int) $id )
			return false;

		if ( !$poll = polldaddy_poll( $args, $id ) )
			return false;

//		$pos = $this->add_request( __FUNCTION__, $poll );
		$pos = $this->add_request( 'updatepoll', $poll );
		$this->send_request();
		if ( !$demand = $this->response_part( $pos ) )
			return $demand;
		if ( !isset( $demand->poll ) )
			return false;
		return $demand->poll;
	} 

	/**
	 * @see polldaddy_poll()
	 * @param int $id Polldaddy Folder ID
	 * @param array $args polldaddy_poll() args
	 * @return false on failure
	 */
	function update_poll_defaults( $folderID,  $args = null ) {
		$folderID = (int) $folderID;
       
		if ( !$poll = new Polldaddy_Poll( $args, compact( 'folderID' ) ) )
			return false;

//		$pos = $this->add_request( __FUNCTION__, $poll );
		$pos = $this->add_request( 'updatepolldefaults', $poll );
		$this->send_request();
		return empty( $this->errors );
	}

  /* Poll Results */
	/**
	 * @param int $id Polldaddy Poll ID
	 * @return array|false Polldaddy Result or false on failure
	 */
	function get_poll_results( $id ) {
		if ( !$id = (int) $id )
			return false;
			
			$start = 0;
			$end = 2;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Poll_Result( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'getpollresults', new Polldaddy_Poll( null, compact( 'id' ) ) );
		//Optionally if you want to list other answers... 
		//$pos = $this->add_request( 'getpollresults', new Polldaddy_List( null, compact( 'id', 'start', 'end' ) ) );
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
						$others = array( $demand->result->otherAnswers->otherAnswer );
				}
			}
			return (object) compact( 'answers', 'others' );
		}
		return false;
	}

	/**
	 * @param int $id Polldaddy Poll ID
	 * @return bool success
	 */
	function reset_poll_results( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Poll_Result( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'resetpollresults', new Polldaddy_Poll( null, compact( 'id' ) ) );
		$this->send_request();

		return empty( $this->errors );
	}

  /* Poll Comments */
	/**
	 * @param int $id Polldaddy Poll ID
	 * @return array|false Polldaddy Comments or false on failure
	 */
	function get_poll_comments( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Comments( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'getpollcomments', new Polldaddy_Poll( null, compact( 'id' ) ) );
		$this->send_request();

		$demand = $this->response_part( $pos );
		
		if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->comments ) ) {
			if ( isset( $demand->comments->comment ) && !is_array( $demand->comments->comment ) ) {
				if ( $demand->comments->comment )
					$demand->comments->comment = array( $demand->comments->comment );
				else
					$demand->comments->comment = array();
			}
			return $demand->comments;
		}
		return false;
	}

	/**
	 * @see polldaddy_comment()
	 * @param array $args polldaddy_comment() args
	 * @return bool success
	 */
	function moderate_comment( $id, $args = null ) {
		if ( !$id = (int) $id )
			return false;
			
		if ( !$comment = polldaddy_comment( $args, $id ) )
			return false;

//		$this->add_request( __FUNCTION__, new Polldaddy_Comments( $comments ) );
		$this->add_request( 'moderatecomment', $comment);
		$this->send_request();
		
		return empty( $this->errors );	
	}

	/* Languages */
		/**
		 * @return array|false Polldaddy Languages or false on failure
		 */
	function get_languages() {

//		$pos = $this->add_request( __FUNCTION__, null );
		$pos = $this->add_request( 'getlanguages', null );
		$this->send_request();

		$demand = $this->response_part( $pos );
		
		if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->languages ) ) {
			if ( isset( $demand->languages->language ) && !is_array( $demand->languages->language ) ) {
				if ( $demand->languages->language )
					$demand->languages->language  = array( $demand->languages->language );
				else
					$demand->languages->language  = array();
			}
			return $demand->languages->language;
		}
		return false;
	}

   /* Language Packs */
	/**
	 * @return array|false Polldaddy Packs or false on failure
	 */
	function get_packs() {

//		$pos = $this->add_request( __FUNCTION__, null );
		$pos = $this->add_request( 'getpacks', null );
		$this->send_request();

		$demand = $this->response_part( $pos );
		if ( isset( $demand->packs ) ) {
			if ( isset( $demand->packs->pack ) ) {
				if ( !is_array( $demand->packs->pack ) )
					$demand->packs->pack = array( $demand->packs->pack );
			}
			return $demand->packs;
		}
		return false;
	}
	
	/**
	 * @param int $id Polldaddy Pack ID
	 * @return array|false Polldaddy Pack or false on failure
	 */
	function get_pack( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Pack( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'getpack', new Polldaddy_Pack( null, compact( 'id' ) ) );
		$this->send_request();

		$demand = $this->response_part( $pos );
		
		if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->pack ) ) {
			return $demand->pack;
		}
		return false;
	}

	/**
	 * @param int $id Polldaddy Pack ID
	 * @return bool success
	 */
	function delete_pack( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Pack( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'deletepack', new Polldaddy_Pack( null, compact( 'id' ) ) );
		$this->send_request();

		return empty( $this->errors );
	}

	/**
	 * @see polldaddy_pack()
	 * @param array $args polldaddy_pack() args
	 * @return array|false Polldaddy Pack or false on failure
	 */
	function create_pack( $args = null ) {
		if ( !$pack = polldaddy_pack( $args ) )
			return false;
//		$pos = $this->add_request( __FUNCTION__, $pack );
		$pos = $this->add_request( 'createpack', $pack );
		$this->send_request();
		if ( !$demand = $this->response_part( $pos ) )
			return $demand;
		if ( !isset( $demand->pack ) )
			return false;
		return $demand->pack;
	}

	/**
	 * @see polldaddy_pack()
	 * @param int $id Polldaddy Pack ID
	 * @param array $args polldaddy_pack() args
	 * @return array|false Polldaddy Pack or false on failure
	 */
	function update_pack( $id, $args = null ) {
		if ( !$id = (int) $id )
			return false;

		if ( !$pack = polldaddy_pack( $args, $id ) )
			return false;

//		$pos = $this->add_request( __FUNCTION__, $pack );
		$pos = $this->add_request( 'updatepack', $pack );
		$this->send_request();
		return $this->response_part( $pos );
	}

   /* Styles */
	/**
	 * @return array|false Polldaddy Styles or false on failure
	 */
	function get_styles() {

//		$pos = $this->add_request( __FUNCTION__, null );
		$pos = $this->add_request( 'getstyles', null );
		$this->send_request();

		$demand = $this->response_part( $pos );
		if ( isset( $demand->styles ) ) {
			if ( isset( $demand->styles->style ) ) {
				if ( !is_array( $demand->styles->style ) )
					$demand->styles->style = array( $demand->styles->style );
			}
			return $demand->styles;
		}
		return false;
	}

	/**
	 * @param int $id Polldaddy Style ID
	 * @return array|false Polldaddy Style or false on failure
	 */
	function get_style( $id ) {
		if ( !$id = (int) $id )
			return false;

	//	$pos = $this->add_request( __FUNCTION__, new Polldaddy_Style( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'getstyle', new Polldaddy_Style( null, compact( 'id' ) ) );
		$this->send_request();

		$demand = $this->response_part( $pos );

		if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->style ) ) {
			return $demand->style;
		}
		return false;
	}

	/**
	 * @param int $id Polldaddy Style ID
	 * @return bool success
	 */
	function delete_style( $id ) {
		if ( !$id = (int) $id )
			return false;

//		$pos = $this->add_request( __FUNCTION__, new Polldaddy_Style( null, compact( 'id' ) ) );
		$pos = $this->add_request( 'deletestyle', new Polldaddy_Style( null, compact( 'id' ) ) );
		$this->send_request();

		return empty( $this->errors );
	}

	/**
	 * @see polldaddy_style()
	 * @param array $args polldaddy_style() args
	 * @return array|false Polldaddy Style or false on failure
	 */
	function create_style( $args = null ) {
		if ( !$style = polldaddy_style( $args ) )
			return false;
//		$pos = $this->add_request( __FUNCTION__, $style );
		$pos = $this->add_request( 'createstyle', $style );
		$this->send_request();
		if ( !$demand = $this->response_part( $pos ) )
			return $demand;
		if ( !isset( $demand->style ) )
			return false;
		return $demand->style;
	}

	/**
	 * @see polldaddy_style()
	 * @param int $id Polldaddy Style ID
	 * @param array $args polldaddy_style() args
	 * @return array|false Polldaddy Style or false on failure
	 */
	function update_style( $id, $args = null ) {
		if ( !$id = (int) $id )
			return false;

		if ( !$style = polldaddy_style( $args, $id ) )
			return false;

//		$pos = $this->add_request( __FUNCTION__, $style );
		$pos = $this->add_request( 'updatestyle', $style );
		$this->send_request(30);
		if ( !$demand = $this->response_part( $pos ) )
			return $demand;
		if ( !isset( $demand->style ) )
			return false;
		return $demand->style;
	}

	function get_rating( $id ){
		if ( !$id = (int) $id )
			return false;
		
		$pos = $this->add_request( 'getrating', new Polldaddy_Rating( null , compact( 'id' ) ) );

		$this->send_request();

		$demand = $this->response_part( $pos );

		if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->rating ) ){
			return $demand->rating;
		}
			
		return false;
		
	}
	
	function update_rating( $id, $settings, $type ){

	    if ( !$id = (int) $id )
	        return false;

	    $pos = $this->add_request( 'updaterating', new Polldaddy_Rating( compact( 'settings'  ) , compact( 'id', 'type' ) ) );

	    $this->send_request();

	    $demand = $this->response_part( $pos );

	    if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->rating ) ){
	        return $demand->rating;
	    }

	    return false;

	}

    /* Create Rating 
	 * @param string $name Polldaddy rating name
	 * @param string $type Polldaddy rating type
	 * @return array|false Polldaddy Result or false on failure
	 */

    function create_rating( $name, $type ){

	    $pos = $this->add_request( 'createrating', new Polldaddy_Rating( compact( 'name'  ) , compact( 'type' ) ) );

	    $this->send_request();

	    $demand = $this->response_part( $pos );

	    if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->rating ) ){
	        return $demand->rating;
	    }

	    return false;

    }
	
	
	/* Rating Results */
	/**
	 * @param int $id Polldaddy Poll ID
	 * @param string $period Rating period
	 * @param int $start paging start
	 * @param int $end paging end
	 * @return array|false Polldaddy Rating Result or false on failure
	 */
	function get_rating_results( $id, $period = '90', $start = 0, $end = 2 ) {
		if ( !$id = (int) $id )
			return false;

		$pos = $this->add_request( 'getratingresults', new Polldaddy_List( compact( 'period' ) , compact( 'id', 'start', 'end' ) ) );

		$this->send_request();

		$demand = $this->response_part( $pos );

		if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->rating_result ) ) {
			if ( isset( $demand->rating_result->ratings ) ) {
				if ( isset( $demand->rating_result->ratings->rating ) ) {
					if ( !is_array( $demand->rating_result->ratings->rating ) )
						$demand->rating_result->ratings->rating = array( $demand->rating_result->ratings->rating );
				}
				return $demand->rating_result->ratings;
			}
		}
		return false;
	}
	
	function delete_rating_result( $id, $uid = '' ){
		if ( !$id = (int) $id )
			return false;
		
		$pos = $this->add_request( 'deleteratingresult', new Polldaddy_Rating( compact( 'uid' ) , compact( 'id' ) ) );

		$this->send_request();

		$demand = $this->response_part( $pos );

		if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->rating ) ){
			return $demand->rating;
		}
			
		return false;
		
	}
	
	/* Add Media 
	 * @param string $name Polldaddy media name
	 * @param string $type Polldaddy media type
	 * @param string $size Polldaddy media size
	 * @param string $data Polldaddy media data
	 * @return array|false Polldaddy Media or false on failure
	 */

    function upload_image( $name, $url, $type, $id = 0 ){

	    $pos = $this->add_request( 'uploadimageurl', new Polldaddy_Media( compact( 'name', 'type', 'url'  ) , compact( 'id' ) ) );

	    $this->send_request(30);

	    $demand = $this->response_part( $pos );

	    if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->media ) ){
	        return $demand->media;
	    }

	    return false;
    }
    
    function get_media( $id ){
		if ( !$id = (int) $id )
			return false;
			
	    $pos = $this->add_request( 'getmedia', new Polldaddy_Media( null, compact( 'id' ) ) );

	    $this->send_request();

	    $demand = $this->response_part( $pos );

	    if ( is_a( $demand, 'Ghetto_XML_Object' ) && isset( $demand->media ) ){
	        return $demand->media;
	    }

	    return false;
    }
	
	function get_xml(){
		return array( 'REQUEST' => $this->request_xml, 'RESPONSE' => $this->response_xml );
	}
}

function &polldaddy_activity( $act ) {
	if ( !is_string( $act ) || !$act )
		return false;

	$obj = new Polldaddy_Activity( $act );
	
	return $obj; 
}

/**
 * @param int $id
 * @param string $title
 * @param string $css
 */
function &polldaddy_style( $args = null, $id = null, $_require_data = true ) {
	$false = false;
	if ( is_a( $args, 'Polldaddy_Style' ) ) {
		if ( is_null( $id ) )
			return $args;
		if ( !$id = (int) $id )
			return $false;
		$args->_id = $id;
		return $args;
	}

	$defaults = _polldaddy_style_defaults();
	$retro = 0;
	
	if ( !is_null( $args ) ) {
		$args = wp_parse_args( $args, $defaults );

		//if ( $_require_data ) {}
		
		$retro = (int) $args['retro'];
			
		if ( is_null( $id ) )
			$id = $args['id'];
		unset( $args['id'] );
	}

	$obj = new Polldaddy_Style( $args, compact( 'id', 'retro' ) );
	
	return $obj; 
}

function _polldaddy_style_defaults() {
	return array(
		'id' => null,
		'title' => false,
		'css' => false,
		'retro' => 0
	);
}

/**
 * @param int $id
 * @param string $title
 * @param array $phrases
 */
function &polldaddy_pack( $args = null, $id = null, $_require_data = true ) {
	$false = false;
	if ( is_a( $args, 'Polldaddy_Pack' ) ) {
		if ( is_null( $id ) )
			return $args;
		if ( !$id = (int) $id )
			return $false;
		$args->_id = $id;
		return $args;
	}

	$defaults = _polldaddy_pack_defaults();
	$retro = 0;
	
	if ( !is_null( $args ) ) {
		$args = wp_parse_args( $args, $defaults );

		//if ( $_require_data ) {}
		
		$retro = (int) $args['retro'];

		$args['pack'] = new Custom_Pack( $args['pack'] );
			
		if ( is_null( $id ) )
			$id = $args['id'];
		unset( $args['id'] );
	}

	$obj = new Polldaddy_Pack( $args, compact( 'id', 'retro' ) );
	
	return $obj; 
}

function _polldaddy_pack_defaults() {
	return array(
		'id' => null,
		'retro' => 0,
		'pack' => array()
	);
}

function _polldaddy_pack_phrases_defaults() {
	return array(
		'id' => null,
		'type' => null,
		'title' => false,
		'phrase' => array()
	);
}

function &polldaddy_custom_phrase( $phrase, $phraseID = null ) {
	if ( !is_string( $phrase ) || !$phrase )
		return false;

	$obj = new Custom_Pack_Phrase( $phrase, compact( 'phraseID' ) );
	
	return $obj;
}

function polldaddy_email( $args = null, $id = null, $_require_data = true ) {
	if ( is_a( $args, 'Polldaddy_Email' ) ) {
		if ( is_null( $id ) )
			return $args;
		if ( !$id = (int) $id )
			return $false;
		$args->_id = $id;
		return $args;
	}

	$defaults = array();

	if ( !is_null( $args ) ) {
		$args = wp_parse_args( $args, $defaults );

		if ( $_require_data ) {
			if ( !isset( $args['address'] ) || !is_string( $args['address'] ) )
				return false;
		}

		// Check email is an email address
		if ( preg_match( '/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i', $args['address'], $matches ) == 0 )
			return false;
	}

	return new Polldaddy_Email( $args, compact( 'id' ) );
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
	if ( is_a( $args, 'Polldaddy_Account' ) )
		return $args;

	$defaults = _polldaddy_account_defaults();

	$args = wp_parse_args( $args, $defaults );

	foreach ( array( 'userName', 'email' ) as $required )
		if ( !is_string( $args[$required] ) || !$args[$required] )
			return $false;

	$obj = new Polldaddy_Account( $args );
	
	return $obj;
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
	if ( is_a( $args, 'Polldaddy_Poll' ) ) {
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

		foreach ( array( 'multipleChoice', 'randomiseAnswers', 'makePublic', 'otherAnswer', 'closePoll', 'closePollNow', 'sharing' ) as $bool ) {
			if ( 'no' !== $args[$bool] && 'yes' !== $args[$bool] )
				$args[$bool] = $defaults[$bool];
		}
		
		global $wpdb;		
		$public = (int) $wpdb->get_var( $wpdb->prepare( "SELECT public FROM wp_blogs WHERE blog_id = %d", $wpdb->blogid ) );
		if( $public == -1 )
			$args['makePublic'] = 'no';

		foreach ( array( 'styleID', 'packID', 'folderID', 'languageID', 'choices', 'blockExpiration' ) as $int )
			if ( !is_numeric( $args[$int] ) )
				$args[$bool] = $defaults[$int];

		if ( !in_array( $args['resultsType'], array( 'show', 'percent', 'hide' ) ) )
			$args['resultsType'] = $defaults['resultsType'];

		if ( !in_array( $args['blockRepeatVotersType'], array( 'off', 'cookie', 'cookieip' ) ) )
			$args['blockRepeatVotersType'] = $defaults['blockRepeatVotersType'];

		if ( !in_array( $args['comments'], array( 'off', 'allow', 'moderate' ) ) )
			$args['comments'] = $defaults['comments'];

		if ( is_numeric( $args['closeDate'] ) )
			$args['closeDate'] = gmdate( 'Y-m-d H:i:s', $args['closeDate'] );
		if ( !$args['closeDate'] )
			$args['closeDate'] = gmdate( 'Y-m-d H:i:s' );

		$args['answers'] = new Polldaddy_Poll_Answers( array( 'answer' => $args['answers'] ) );

		if ( is_null( $id ) )
			$id = $args['id'];
		unset( $args['id'] );
	}
	
	$obj = new Polldaddy_Poll( $args, compact( 'id' ) );

	return $obj;
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
		'blockExpiration' => 0,
		'comments' => 'allow',
		'makePublic' => 'yes',
		'closePoll' => 'no',
		'closePollNow' => 'no',
		'sharing' => 'yes',
		'closeDate' => gmdate( 'Y-m-d H:i:s' ),
		'styleID' => 0,
		'packID' => 0,
		'folderID' => 0,
		'languageID' => _polldaddy_poll_default_language_id(),
		'parentID' => (int) $GLOBALS['blog_id'],
		'mediaCode' => '',
		'mediaType' => 0,
		'choices' => 0,
		'answers' => array()
	);
}

/**
 * @param int $id
 * @param int $type
 */
function &polldaddy_comment( $args = null, $id = null ) {
	$defaults = _polldaddy_comment_defaults();

	$atts = wp_parse_args( $args, $defaults );
	
	$obj = new Polldaddy_Comment( null, $atts );
	
	return $obj; 
}

function _polldaddy_comment_defaults() {
	return array(
		'id' => null,
		'method' => 0
	);
}

/**
 * @param int $id
 * @param array $comment
 */
function &polldaddy_comments( $args = null, $id = null ) {
	$false = false;
	if ( is_a( $args, 'Polldaddy_Comments' ) )
		return $args;

	$defaults = _polldaddy_comments_defaults();

	$args = wp_parse_args( $args, $defaults );

	if ( is_null( $id ) )
		$id = $args['id'];
	unset( $args['id'] );
	
	$obj = new Polldaddy_Comments( $args, compact( 'id' ) );
	
	return $obj; 
}

function _polldaddy_comments_defaults() {
	return array(
		'id' => null,
		'comment' => array()
	);
}

if ( !function_exists( '_polldaddy_poll_default_language_id' ) ) :
function _polldaddy_poll_default_language_id() {
	return 1;
}
endif;

function &polldaddy_poll_answer( $args, $id = null ) {
	$answer = false;
	
	if ( is_string( $args['text'] ) && strlen($args['text'] ) > 0 ){
		$answer = new Polldaddy_Poll_Answer( $args, compact( 'id' ) );
	}

	return $answer;
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
?>
