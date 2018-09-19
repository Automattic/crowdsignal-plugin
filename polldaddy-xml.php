<?php

class Ghetto_XML_Object {
	function __construct( $args = null, $attributes = null ) {
		if ( get_object_vars( $this ) )
			$this->___restrict = true;
		else
			$this->___restrict = false;

		if ( !is_null( $args ) )
			$this->set_args( $args );
		if ( !is_array( $attributes ) )
			return false;

		$atts = array();
		foreach ( $attributes as $key => $value )
			$atts["_$key"] = $value;

		$this->set_args( $atts );
	}

	function xml( $prepend_ns = true, $pad = 0 ) {
		$x = '';
		$atts = get_object_vars( $this );

		$ns = $atts['___ns'];
		if ( $prepend_ns )
			$name = "$ns:{$atts['___name']}";
		else
			$name = $atts['___name'];

		$_prepend_ns = $prepend_ns;

		$prepend_ns = 'all' === $prepend_ns;

		// added this to remove the Warning ( PHP Notice:  Undefined index ) in following condition
		if ( !isset( $atts['___cdata'] ) )
			$atts['___cdata'] = '';
		
		if ( !$cdata = $atts['___cdata'] )
			$cdata = array();

		$x = "<$name";
		
		if ( isset( $atts['___content'] ) ) {
			$inner = in_array( '___content', $cdata ) ? '<![CDATA[' . $atts['___content'] . ']]>' : $atts['___content'];
			$empty = false;
		} else {
			$inner = "\n";
			$empty = true;
		}

		unset($atts['___ns'], $atts['___name'], $atts['___content'], $atts['___ns_full'], $atts['___restrict'], $atts['___cdata']);

		$_pad = str_repeat( "\t", $pad + 1 );

		foreach ( $atts as $key => $value ) {			
			if ( is_null( $value ) )
				continue;
			if ( '_' == $key[0] ) {
				$key = substr( $key, 1 );
				$x .= " $key='$value'";
				continue;
			}

			$_key = $key;
			if ( $prepend_ns )
				$key = "$ns:$key";

			$empty = false;
			if ( false === $value ) {
				$inner .= "$_pad<$key />\n";
			}  
			elseif ( is_array( $value ) ) {
				foreach ( $value as $array_value ) {
					if ( is_a( $array_value, 'Ghetto_XML_Object' ) )
						$inner .= $_pad . $array_value->xml( $_prepend_ns, $pad + 1 ) . "\n";
					else
						$inner .= in_array( $_key, $cdata ) ? "$_pad<$key>" . '<![CDATA[' . $array_value . ']]>' . "</$key>\n" : "$_pad<$key>$array_value</$key>\n";
				}
			} 
			else {
				if ( is_a( $value, 'Ghetto_XML_Object' ) )
					$inner .= $_pad . $value->xml( $_prepend_ns, $pad + 1 ) . "\n";
				else{
					$inner .= in_array( $_key, $cdata ) ? "$_pad<$key>" . '<![CDATA[' . $value . ']]>' . "</$key>\n" : "$_pad<$key>$value</$key>\n";
				}
			}
		}
		if ( $empty )
			return $x . ' />';
		if ( "\n" == substr( $inner, -1 ) )
			$inner .= str_repeat( "\t", $pad  );

		return $x . ">$inner</$name>";
	}

	function set_args( $array ) {
		if ( is_scalar( $array ) ) {
			$this->___content = $array;
			return;
		}

		$atts = get_object_vars( $this );
		foreach ( $array as $key => $value ) {
			if ( 0 === strpos( $key, $this->___ns_full ) )
				$key = substr( $key, strlen( $this->___ns_full ) + 1 );
			if ( is_null( $value ) || ( $this->___restrict && ! array_key_exists( $key, $atts ) ) )
				continue;

			$this->$key = $value;
		}
	}
}

class Polldaddy_XML_Object extends Ghetto_XML_Object {
	var $___ns;
	var $___ns_full;

	function __construct( $args = null, $attributes = null ) {
		$this->___ns = 'pd';
		$this->___ns_full = polldaddy_api_url( '/pdapi.xsd' );

		parent::__construct( $args, $attributes );
	}
}

class Polldaddy_XML_Root extends Polldaddy_XML_Object {
	function xml( $prepend_ns = true, $pad = 0 ) {
		$xml = parent::xml( $prepend_ns, $pad );
		if ( !$pad ) {
			$pos = strpos( $xml, '>' );
			$xml = substr_replace( $xml, " xmlns:$this->___ns='$this->___ns_full'", $pos, 0 );
		}
		return $xml;
	}
}

class Polldaddy_Access extends Polldaddy_XML_Root {
	var $___name = 'pdAccess';

	var $_partnerGUID;
	var $_partnerUserID;

	var $demands;
}

class Polldaddy_Initiate extends Polldaddy_XML_Root {
	var $___cdata = array( 'Email', 'Password' );
	var $___name = 'pdInitiate';

	var $_partnerGUID;
	var $_partnerUserID;

	var $Email;
	var $Password;	
}

class Polldaddy_Request extends Polldaddy_XML_Root {
	var $___name = 'pdRequest';

	var $_partnerGUID;
	var $_version;
	var $_admin;

	var $userCode;
	var $demands;
}

class Polldaddy_Response extends Polldaddy_XML_Root {
	var $___name = 'pdResponse';

	var $_partnerGUID;
	var $_partnerUserID;

	var $userCode;
	var $demands;
	var $errors;
	var $queries;
}

class Polldaddy_Errors extends Polldaddy_XML_Object {
	var $___name = 'errors';

	var $error;
}

class Polldaddy_Error extends Polldaddy_XML_Object {
	var $___cdata = array( '___content' );
	var $___name = 'error';

	var $___content;

	var $_id;
}

class Polldaddy_Queries extends Polldaddy_XML_Object {
	var $___name = 'queries';

	var $query;
}

class Polldaddy_Query extends Polldaddy_XML_Object {
	var $___cdata = array( 'text' );
	var $___name = 'query';

	var $_id;
	
	var $time;
	var $text;
	var $caller;
}

class Polldaddy_Demands extends Polldaddy_XML_Object {
	var $___name = 'demands';

	var $demand;
}

class Polldaddy_Demand extends Polldaddy_XML_Object {
	var $___name = 'demand';

	var $_id;

	var $account;
	var $poll;
	var $polls;
	var $emailAddress;
	var $message;
	var $list;
	var $search;
	var $result;
	var $comments; //need to add an request object for each new type 
	var $comment;
	var $extensions;
	var $folders;
	var $styles;
	var $style;
	var $packs;
	var $pack;
	var $languages;
	var $activity;
	var $rating_result;
	var $rating;
	var $nonce;
	var $partner;
	var $media;
}

class Polldaddy_Partner extends Polldaddy_XML_Object {
  var $___cdata = array( 'name' );
	var $___name = 'partner';

	var $_role;
	var $_users;
	
	var $name;
}

class Polldaddy_Account extends Polldaddy_XML_Object {
	var $___cdata = array( 'userName', 'email', 'password', 'firstName', 'lastName', 'websiteURL', 'avatarURL', 'bio' );
	var $___name = 'account';

	var $userName;
	var $email;
	var $password;
	var $firstName;
	var $lastName;
	var $countryCode;
	var $gender;
	var $yearOfBirth;
	var $websiteURL;
	var $avatarURL;
	var $bio;
	var $src;
}

class Polldaddy_List extends Polldaddy_XML_Object {
	var $___name = 'list';

	var $_start;
	var $_end;
	var $_id;
	
	var $period;
}

class Polldaddy_Polls extends Polldaddy_XML_Object {
	var $___name = 'polls';

	var $_total;

	var $poll;
}

class Polldaddy_Search extends Polldaddy_XML_Object {
	var $___cdata = array( '___content' );
	var $___name = 'search';

	var $___content;

	var $poll;
}

class Polldaddy_Poll extends Polldaddy_XML_Object {
	var $___cdata = array( '___content', 'question', 'mediaCode', 'url' );
	var $___name = 'poll';

	var $___content;

	var $_id;
	var $_created;
	var $_responses;
	var $_folderID;
	var $_owner;
	var $_closed;

	var $question;
	var $multipleChoice;
	var $randomiseAnswers;
	var $otherAnswer;
	var $resultsType;
	var $blockRepeatVotersType;  
	var $blockExpiration;
	var $comments;
	var $makePublic;
	var $closePoll;
	var $closePollNow;
	var $closeDate;
	var $styleID;
	var $packID;
	var $folderID;
	var $languageID;
	var $parentID;
	var $keyword;
	var $sharing;
	var $rank;
	var $url;
	var $choices;
	var $mediaType; // new
	var $mediaCode; // new
	var $answers;
}

class Polldaddy_Poll_Result extends Polldaddy_XML_Object {
	var $___name = 'result';

	var $_id;

	var $answers;
	var $otherAnswers;
}

class Polldaddy_Poll_Answers extends Polldaddy_XML_Object {
	var $___name = 'answers';

	var $answer;
}

class Polldaddy_Poll_Answer extends Polldaddy_XML_Object {
	var $___cdata = array( '___content', 'text', 'mediaCode' );
	var $___name = 'answer';

	var $_id;
	var $_total;
	var $_percent;
	
	var $___content;
	
	var $text;	//removed ___content and replaced it with text node
	var $mediaType; // new
	var $mediaCode; // new
}

class Polldaddy_Other_Answers extends Polldaddy_XML_Object {
	var $___name = 'otherAnswers';

	var $otherAnswer;
}

class Polldaddy_Other_Answer extends Polldaddy_XML_Object {
	var $___cdata = array( '___content' );
	var $___name = 'otherAnswer';

	var $___content;
}

class Polldaddy_Comments extends Polldaddy_XML_Object {
	var $___cdata = array( '___content' );
	var $___name = 'comments';
	
	var $___content;

	var $_id;

	var $comment;
}

class Polldaddy_Comment extends Polldaddy_XML_Object {
	var $___cdata = array( 'name', 'email', 'text', 'url' );
	var $___name = 'comment';

	var $_id; //_ means variable corresponds to an attribute
	var $_method;
	var $_type;

	var $poll; // without _ means variable corresponds to an element
	var $name;
	var $email;
	var $text;
	var $url;
	var $date;
	var $ip;
}

class Polldaddy_Extensions extends Polldaddy_XML_Object {
	var $___name = 'extensions';
	
	var $folders;
	var $styles;
	var $packs;
	var $languages;
}

class Polldaddy_Folders extends Polldaddy_XML_Object {
	var $___name = 'folders';
	
	var $folder;
}

class Polldaddy_Folder extends Polldaddy_XML_Object {
	var $___cdata = array( '___content' );
	var $___name = 'folder';
	
	var $___content;
	
	var $_id;
}

class Polldaddy_Styles extends Polldaddy_XML_Object {
	var $___name = 'styles';
	
	var $style;
}

class Polldaddy_Style extends Polldaddy_XML_Object {
	var $___cdata = array( 'title', 'css' );
	var $___name = 'style';
	
	var $_id;
	var $_type;
	var $_retro;
	var $_direction;
	
	var $title;
	var $date;	
	var $css;
}

class Polldaddy_Packs extends Polldaddy_XML_Object {
	var $___name = 'packs';
	
	var $pack;
}

class Polldaddy_Pack extends Polldaddy_XML_Object {
	var $___name = 'pack';
	
	var $_id;
	var $_date;
	var $_retro;
	
	var $pack;
}

class Custom_Pack extends Polldaddy_XML_Object {
	var $___name = 'pack';
	
	var $_type = 'user'; //type attribute is constant (for now)
	
	var $title;
	var $phrase;
	
	function xml( $prepend_ns = true, $pad = 0 ) {
		$xml = parent::xml( false, $pad );
		return $xml;
	}
}

class Custom_Pack_Phrase extends Polldaddy_XML_Object {
	var $___cdata = array( '___content' );
	var $___name = 'phrase';
	
	var $___content;
	
	var $_phraseID;
	
	function xml( $prepend_ns = true, $pad = 0 ) {
		$xml = parent::xml( false, $pad );
		return $xml;
	}
}

class Polldaddy_Languages extends Polldaddy_XML_Object {
	var $___name = 'languages';
	
	var $language;
}

class Polldaddy_Language extends Polldaddy_XML_Object {
	var $___cdata = array( '___content' );
	var $___name = 'language';
	
	var $___content;
	
	var $_id;
}

class Polldaddy_Activity extends Polldaddy_XML_Object {
	var $___cdata = array( '___content' );
	var $___name = 'activity';
	
	var $___content;
}

class Polldaddy_Nonce extends Polldaddy_XML_Object {
	var $___cdata = array( 'text', 'action' );
	var $___name = 'nonce';
	
	var $text;
	var $action;
	var $userCode;
}

class Polldaddy_Rating_Result extends Polldaddy_XML_Object {
	var $___name = 'rating_result';

	var $_id;
	
	var $ratings;
}

class Polldaddy_Ratings extends Polldaddy_XML_Object {
	var $___name = 'ratings';

	var $_total;
	var $rating;
}

class Polldaddy_Rating extends Polldaddy_XML_Object {
	var $___name = 'rating';
    	var $___cdata = array( 'settings', 'name', 'title', 'permalink' );

	var $_id;
	
	var $_type;
	var $_votes;
	var $uid;
	var $total1;
	var $total2;
	var $total3;
	var $total4;
	var $total5;
	var $average_rating;
	var $date;
	var $title;
	var $permalink;
	
	var $name;
	var $folder_id;
	var $settings;
}

class Polldaddy_Email extends Polldaddy_XML_Object {
	var $___cdata = array( 'custom' );
	var $___name = 'emailAddress';

	var $_id;
	var $_owner;

	var $folderID;
	var $address;
	var $firstname;
	var $lastname;
	var $custom;
	var $status;
}

class Polldaddy_Email_Message extends Polldaddy_XML_Object {
	var $___cdata = array( 'text' );
	var $___name = 'message';

	var $_id;
	var $_owner;

	var $text;
	var $groups;
}

class Polldaddy_Media extends Polldaddy_XML_Object {
	var $___cdata = array( 'name', 'data', 'type', 'upload_result', 'img', 'img_small', 'url' );
	var $___name = 'media';

	var $_size;
	var $_id;
	
	var $name;
	var $type;
	var $ext;
	var $data;
	var $upload_result;
	var $img;
	var $img_small;
	var $url;
}

class Polldaddy_XML_Parser {
	var $parser;
	var $polldaddy_objects = array(
		'pdapi.xsd:pdAccess'      => 'Polldaddy_Access',
		'pdapi.xsd:pdInitiate'    => 'Polldaddy_Initiate',
		'pdapi.xsd:pdRequest'     => 'Polldaddy_Request',
		'pdapi.xsd:pdResponse'    => 'Polldaddy_Response',
		'pdapi.xsd:errors'        => 'Polldaddy_Errors',
		'pdapi.xsd:error'         => 'Polldaddy_Error',
		'pdapi.xsd:demands'       => 'Polldaddy_Demands',
		'pdapi.xsd:demand'        => 'Polldaddy_Demand',
		'pdapi.xsd:queries'       => 'Polldaddy_Queries',
		'pdapi.xsd:query'         => 'Polldaddy_Query',
		'pdapi.xsd:account'       => 'Polldaddy_Account',
		'pdapi.xsd:list'          => 'Polldaddy_List',
		'pdapi.xsd:polls'         => 'Polldaddy_Polls',
		'pdapi.xsd:search'        => 'Polldaddy_Search',
		'pdapi.xsd:poll'          => 'Polldaddy_Poll',
		'pdapi.xsd:emailAddress'  => 'Polldaddy_Email',
		'pdapi.xsd:message'       => 'Polldaddy_Email_Message',
		'pdapi.xsd:answers'       => 'Polldaddy_Poll_Answers',
		'pdapi.xsd:answer'        => 'Polldaddy_Poll_Answer',
		'pdapi.xsd:otherAnswers'  => 'Polldaddy_Other_Answers',
		'pdapi.xsd:result'        => 'Polldaddy_Poll_Result',
		'pdapi.xsd:comments'      => 'Polldaddy_Comments',
		'pdapi.xsd:comment'       => 'Polldaddy_Comment',
		'pdapi.xsd:extensions'    => 'Polldaddy_Extensions',
		'pdapi.xsd:folders'       => 'Polldaddy_Folders',
		'pdapi.xsd:folder'        => 'Polldaddy_Folder',
		'pdapi.xsd:styles'        => 'Polldaddy_Styles',
		'pdapi.xsd:style'         => 'Polldaddy_Style',
		'pdapi.xsd:packs'         => 'Polldaddy_Packs',
		'pdapi.xsd:pack'          => 'Polldaddy_Pack',
		'pdapi.xsd:languages'     => 'Polldaddy_Languages',
		'pdapi.xsd:language'      => 'Polldaddy_Language',
		'pdapi.xsd:activity'      => 'Polldaddy_Activity',
		'pdapi.xsd:rating_result' => 'Polldaddy_Rating_Result',
		'pdapi.xsd:ratings'       => 'Polldaddy_Ratings',
		'pdapi.xsd:rating'        => 'Polldaddy_Rating',
		'pdapi.xsd:nonce'         => 'Polldaddy_Nonce',
		'pdapi.xsd:partner'       => 'Polldaddy_Partner',
		'pdapi.xsd:media'         => 'Polldaddy_Media',
		'pack'                    => 'Custom_Pack',
		'phrase'                  => 'Custom_Pack_Phrase'
	);// the parser matches the tag names to the class name and creates an object defined by that class

	function get_polldaddy_object( $tag ) {
		preg_match(
			sprintf( '#%s/(.*)#', polldaddy_api_url( '/' ) ),
			$tag,
			$matches
		);

		if ( ! empty( $matches ) && array_key_exists( $matches[ 1 ], $this->polldaddy_objects ) ) {
			return $this->polldaddy_objects[ $matches[ 1 ] ];
		}

		if ( array_key_exists( $tag, $this->polldaddy_objects ) ) {
			return $this->polldaddy_objects[ $tag ];
		}

		return null;
	}

	var $object_stack = array();
	var $object_pos = null;

	var $objects = array();

	function __construct( $xml = null ) {
		if ( is_null( $xml ) )
			return;

		return $this->parse( $xml );
	}

	function parse( $xml ) {
		$this->parser = xml_parser_create_ns( 'UTF-8' );
		xml_set_object( $this->parser, $this );
		xml_set_element_handler( $this->parser, 'tag_open', 'tag_close' );
		xml_set_character_data_handler( $this->parser, 'text' );
		xml_parser_set_option( $this->parser, XML_OPTION_CASE_FOLDING, 0 );
		xml_parser_set_option( $this->parser, XML_OPTION_SKIP_WHITE, 1 );
		xml_parse( $this->parser, $xml );
		xml_parser_free( $this->parser );
		return $this->objects;
	}

	function tag_open( &$parser, $tag, $attributes ) {
		$object_pos = $this->object_pos;
		if ( $this->object_stack ) {
			if ( isset( $this->object_stack[$object_pos]['args'][$tag] ) ) {
				if ( is_array( $this->object_stack[$object_pos]['args'][$tag] ) ) {
					$this->object_stack[$object_pos]['args'][$tag][] = false;
				} else {
					$this->object_stack[$object_pos]['args'][$tag] = array( $this->object_stack[$object_pos]['args'][$tag], false );
				}
				end( $this->object_stack[$object_pos]['args'][$tag] );
				$this->object_stack[$object_pos]['args_tag_pos'] = key( $this->object_stack[$object_pos]['args'][$tag] );
			} else {
				$this->object_stack[$object_pos]['args'][$tag] = false;
			}
			$this->object_stack[$object_pos]['args_tag'] = $tag;
		}

		if ( $this->get_polldaddy_object( $tag ) ) {
			$this->object_stack[] = array(
				'tag' => $tag,
				'atts' => $attributes,
				'args' => array(),
				'parent' => $this->object_pos,
				'args_tag' => null,
				'args_tag_pos' => null
			);
			end( $this->object_stack );
			$this->object_pos = key( $this->object_stack );
		}
	}

	function text( &$parser, $text ) {
		if ( !$this->object_stack )
			return;

		$text = trim( $text );
		if ( !strlen( $text ) )
			return;

		if ( $this->object_stack[$this->object_pos]['args_tag_pos'] ) {
			if ( isset($this->object_stack[$this->object_pos]['args'][$this->object_stack[$this->object_pos]['args_tag']][$this->object_stack[$this->object_pos]['args_tag_pos']]) )
				$this->object_stack[$this->object_pos]['args'][$this->object_stack[$this->object_pos]['args_tag']][$this->object_stack[$this->object_pos]['args_tag_pos']] .= $text;
			else
				$this->object_stack[$this->object_pos]['args'][$this->object_stack[$this->object_pos]['args_tag']][$this->object_stack[$this->object_pos]['args_tag_pos']] = $text;
		} elseif ( $this->object_stack[$this->object_pos]['args_tag'] ) {
			if ( isset($this->object_stack[$this->object_pos]['args'][$this->object_stack[$this->object_pos]['args_tag']]) )
				$this->object_stack[$this->object_pos]['args'][$this->object_stack[$this->object_pos]['args_tag']] .= $text;
			else
				$this->object_stack[$this->object_pos]['args'][$this->object_stack[$this->object_pos]['args_tag']] = $text;
		} else {
			if ( isset($this->object_stack[$this->object_pos]['args']['___content']) )
				$this->object_stack[$this->object_pos]['args']['___content'] .= $text;
			else	
				$this->object_stack[$this->object_pos]['args']['___content'] = $text;
		}
	}

	function tag_close( &$parser, $tag ) {
		if ( $this->get_polldaddy_object( $tag ) ) {
			if ( $tag !== $this->object_stack[$this->object_pos]['tag'] )
				die( 'damn' );

			$new = $this->get_polldaddy_object( $tag );
			$new_object = new $new( $this->object_stack[$this->object_pos]['args'], $this->object_stack[$this->object_pos]['atts'] );
                                                                                                                                
			if ( is_numeric( $this->object_stack[$this->object_pos]['parent'] ) ) {
				$this->object_pos = $this->object_stack[$this->object_pos]['parent'];
				if ( $this->object_stack[$this->object_pos]['args_tag_pos'] ) {
					$this->object_stack[$this->object_pos]['args'][$this->object_stack[$this->object_pos]['args_tag']][$this->object_stack[$this->object_pos]['args_tag_pos']] = $new_object;
				} elseif ( $this->object_stack[$this->object_pos]['args_tag'] ) {
					$this->object_stack[$this->object_pos]['args'][$this->object_stack[$this->object_pos]['args_tag']] = $new_object;
				}
			} else {
				$this->object_pos = null;
				$this->objects[] = $new_object;
			}

			array_pop( $this->object_stack );
		}
	}
}
?>
