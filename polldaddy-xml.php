<?php

class Ghetto_XML_Object {
	function Ghetto_XML_Object( $args = null, $attributes = null ) {
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

		if ( !$cdata = $atts['___cdata'] )
			$cdata = array();

		$x = "<$name";
		if ( $atts['___content'] ) {
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
			}  elseif ( is_array( $value ) ) {
				foreach ( $value as $array_value ) {
					if ( is_a( $array_value, 'Ghetto_XML_Object' ) )
						$inner .= $_pad . $array_value->xml( $_prepend_ns, $pad + 1 ) . "\n";
					else
						$inner .= in_array( $_key, $cdata ) ? "$_pad<$key>" . '<![CDATA[' . $array_value . ']]>' . "</$key>\n" : "$_pad<$key>$array_value</$key>\n";
				}
			} else {
				if ( is_a( $value, 'Ghetto_XML_Object' ) )
					$inner .= $_pad . $value->xml( $_prepend_ns, $pad + 1 ) . "\n";
				else {
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
			if ( is_null( $value ) || ( $this->___restrict && !array_key_exists( $key, $atts ) ) )
				continue;

			$this->$key = $value;
		}
	}
}

class PollDaddy_XML_Object extends Ghetto_XML_Object {
	var $___ns = 'pd';
	var $___ns_full = 'http://api.polldaddy.com/pdapi.xsd';
}

class PollDaddy_XML_Root extends PollDaddy_XML_Object {
	function xml( $prepend_ns = true, $pad = 0 ) {
		$xml = parent::xml( $prepend_ns, $pad );
		if ( !$pad ) {
			$pos = strpos( $xml, '>' );
			$xml = substr_replace( $xml, " xmlns:$this->___ns='$this->___ns_full'", $pos, 0 );
		}
		return $xml;
	}
}

class PollDaddy_Access extends PollDaddy_XML_Root {
	var $___name = 'pdAccess';

	var $_partnerGUID;
	var $_partnerUserID;

	var $demands;
}

class PollDaddy_Initiate extends PollDaddy_XML_Root {
	var $___cdata = array( 'Email', 'Password' );
	var $___name = 'pdInitiate';

	var $_partnerGUID;
	var $_partnerUserID;

	var $Email;
	var $Password;	
}

class PollDaddy_Request extends PollDaddy_XML_Root {
	var $___name = 'pdRequest';

	var $_partnerGUID;

	var $userCode;
	var $demands;
}

class PollDaddy_Response extends PollDaddy_XML_Root {
	var $___name = 'pdResponse';

	var $_partnerGUID;

	var $userCode;
	var $demands;
	var $errors;
}

class PollDaddy_Errors extends PollDaddy_XML_Object {
	var $___name = 'errors';

	var $error;
}

class PollDaddy_Error extends PollDaddy_XML_Object {
	var $___name = 'error';

	var $___content;

	var $_id;
}

class PollDaddy_Demands extends PollDaddy_XML_Object {
	var $___name = 'demands';

	var $demand;
}

class PollDaddy_Demand extends PollDaddy_XML_Object {
	var $___name = 'demand';

	var $_id;

	var $account;
	var $poll;
	var $polls;
	var $list;
	var $search;
	var $result;
	var $styles;
	var $style;
}

class PollDaddy_Account extends PollDaddy_XML_Object {
	var $___cdata = array( 'userName', 'password', 'firstName', 'lastName', 'websiteURL', 'avatarURL', 'bio' );
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
}

class PollDaddy_List extends PollDaddy_XML_Object {
	var $___name = 'list';

	var $_start;
	var $_end;
	var $_id;
}

class PollDaddy_Polls extends PollDaddy_XML_Object {
	var $___name = 'polls';

	var $_total;

	var $poll;
}

class PollDaddy_Search extends PollDaddy_XML_Object {
	var $___cdata = array( '___content' );
	var $___name = 'search';

	var $___content;

	var $poll;
}

class PollDaddy_Poll extends PollDaddy_XML_Object {
	var $___cdata = array( '___content', 'question' );
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
	var $answers;

	var $sharing;
	var $rank;
	var $url;
}

class PollDaddy_Poll_Answers extends PollDaddy_XML_Object {
	var $___name = 'answers';

	var $answer;
}

class PollDaddy_Poll_Answer extends PollDaddy_XML_Object {
	var $___cdata = array( '___content' );
	var $___name = 'answer';

	var $___content;

	var $_id;
	var $_total;
	var $_percent;
}

class PollDaddy_Poll_Result extends PollDaddy_XML_Object {
	var $___name = 'result';

	var $_id;

	var $answers;
	var $otherAnswers;
}

class PollDaddy_Other_Answers extends PollDaddy_XML_Object {
	var $___name = 'otherAnswers';

	var $otherAnswer;
}

class PollDaddy_Styles extends PollDaddy_XML_Object {
	var $___name = 'styles';
	
	var $style;
}

class PollDaddy_Style extends PollDaddy_XML_Object {
	var $___cdata = array( 'title', 'css' );
	var $___name = 'style';
	
	var $_id;
	var $_type;
	
	var $title;
	var $date;	
	var $css;
}

class PollDaddy_XML_Parser {
	var $parser;
	var $polldaddy_objects = array(
		'http://api.polldaddy.com/pdapi.xsd:pdAccess' => 'PollDaddy_Access',
		'http://api.polldaddy.com/pdapi.xsd:pdInitiate' => 'PollDaddy_Initiate',
		'http://api.polldaddy.com/pdapi.xsd:pdRequest' => 'PollDaddy_Request',
		'http://api.polldaddy.com/pdapi.xsd:pdResponse' => 'PollDaddy_Response',
		'http://api.polldaddy.com/pdapi.xsd:errors' => 'PollDaddy_Errors',
		'http://api.polldaddy.com/pdapi.xsd:error' => 'PollDaddy_Error',
		'http://api.polldaddy.com/pdapi.xsd:demands' => 'PollDaddy_Demands',
		'http://api.polldaddy.com/pdapi.xsd:demand' => 'PollDaddy_Demand',
		'http://api.polldaddy.com/pdapi.xsd:account' => 'PollDaddy_Account',
		'http://api.polldaddy.com/pdapi.xsd:list' => 'PollDaddy_List',
		'http://api.polldaddy.com/pdapi.xsd:polls' => 'PollDaddy_Polls',
		'http://api.polldaddy.com/pdapi.xsd:search' => 'PollDaddy_Search',
		'http://api.polldaddy.com/pdapi.xsd:poll' => 'PollDaddy_Poll',
		'http://api.polldaddy.com/pdapi.xsd:answers' => 'PollDaddy_Poll_Answers',
		'http://api.polldaddy.com/pdapi.xsd:answer' => 'PollDaddy_Poll_Answer',
		'http://api.polldaddy.com/pdapi.xsd:otherAnswers' => 'PollDaddy_Other_Answers',
		'http://api.polldaddy.com/pdapi.xsd:result' => 'PollDaddy_Poll_Result',
		'http://api.polldaddy.com/pdapi.xsd:styles' => 'PollDaddy_Styles',
		'http://api.polldaddy.com/pdapi.xsd:style' => 'PollDaddy_Style'
	);

	var $object_stack = array();
	var $object_pos = null;

	var $objects = array();

	function PollDaddy_XML_Parser( $xml = null ) {
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

		if ( isset( $this->polldaddy_objects[$tag] ) ) {
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
		if ( isset( $this->polldaddy_objects[$tag] ) ) {
			if ( $tag !== $this->object_stack[$this->object_pos]['tag'] )
				die( 'damn' );

			$new = $this->polldaddy_objects[$tag];
			$new_object =& new $new( $this->object_stack[$this->object_pos]['args'], $this->object_stack[$this->object_pos]['atts'] );

			if ( is_numeric( $this->object_stack[$this->object_pos]['parent'] ) ) {
				$this->object_pos = $this->object_stack[$this->object_pos]['parent'];
				if ( $this->object_stack[$this->object_pos]['args_tag_pos'] ) {
					$this->object_stack[$this->object_pos]['args'][$this->object_stack[$this->object_pos]['args_tag']][$this->object_stack[$this->object_pos]['args_tag_pos']] =& $new_object;
				} elseif ( $this->object_stack[$this->object_pos]['args_tag'] ) {
					$this->object_stack[$this->object_pos]['args'][$this->object_stack[$this->object_pos]['args_tag']] =& $new_object;
				}
			} else {
				$this->object_pos = null;
				$this->objects[] =& $new_object;
			}

			array_pop( $this->object_stack );
		}
	}
}
