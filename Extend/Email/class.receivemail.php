<?php

class receiveMail
{

	var $server = '' ;
	var $username = '' ;
	var $password = '' ;
	var $marubox = '' ;
	var $email = '' ;

	// 初始化
	function receiveMail( $username , $password , $EmailAddress ,
		$mailserver = 'localhost' , $servertype = 'pop' , $port = '110' ,
		$ssl = false ) //Constructure
	{
		if( $servertype == 'imap' )
		{
			if( $port == '' )
				$port = '143' ;
			$strConnect = '{' . $mailserver . ':' . $port . '}INBOX' ;
		}
		else
		{
			$strConnect = '{' . $mailserver . ':' . $port . '/pop3/notls' . ($ssl ? "/ssl" : "") . '}INBOX' ;
		}
		$this->server = $strConnect ;
		$this->username = $username ;
		$this->password = $password ;
		$this->email = $EmailAddress ;
	}

	// 连接
	function connect() //Connect To the Mail Box
	{
		$this->marubox = @imap_open( $this->server , $this->username , $this->password ) ;

		if( !$this->marubox )
		{
			echo "Error: Connecting to mail server" ;
			exit ;
		}
	}

	/**
	* listMessages - 获取邮件列表
	* @param $page - 第几页
	* @param $per_page - 每页显示多少封邮件
	* @param $sort - 邮件排序，如：array('by' => 'date', 'direction' => 'desc')
	* */
	function listMessages( $page = 1 , $per_page = 25 , $sort = null )
	{
		$limit = ($per_page * $page) ;
		$start = ($limit - $per_page) + 1 ;
		$start = ($start < 1) ? 1 : $start ;
		$limit = (($limit - $start) != ($per_page - 1)) ? ($start + ($per_page - 1)) : $limit ;
		$info = imap_check( $this->marubox ) ;
		$limit = ($info->Nmsgs < $limit) ? $info->Nmsgs : $limit ;

		if( true === is_array( $sort ) )
		{
			$sorting = array(
				'direction' => array( 'asc' => 0 , 'desc' => 1 ) ,
				'by' => array( 'date' => SORTDATE , 'arrival' => SORTARRIVAL ,
					'from' => SORTFROM , 'subject' => SORTSUBJECT , 'size' => SORTSIZE ) ) ;
			$by = (true === is_int( $by = $sorting[ 'by' ][ $sort[ 0 ] ] )) ? $by : $sorting[ 'by' ][ 'date' ] ;
			$direction = (true === is_int( $direction = $sorting[ 'direction' ][ $sort[ 1 ] ] ))
					? $direction : $sorting[ 'direction' ][ 'desc' ] ;
			$sorted = imap_sort( $this->marubox , $by , $direction ) ;
			$msgs = array_chunk( $sorted , $per_page ) ;
			$msgs = $msgs[ $page - 1 ] ;
		}
		else
		{
			$msgs = range( $start , $limit ) ; //just to keep it consistent
		}
		$result = imap_fetch_overview( $this->marubox , implode( $msgs , ',' ) , 0 ) ;
		if( false === is_array( $result ) )
			return false ;

		foreach( $result as $k => $r )
		{
			$result[ $k ]->subject = $this->_imap_utf8( $r->subject ) ;
			$result[ $k ]->from = $this->_imap_utf8( $r->from ) ;
			$result[ $k ]->to = $this->_imap_utf8( $r->to ) ;
			$result[ $k ]->body = $this->getBody( $r->msgno ) ;
		}
		//sorting!
		if( true === is_array( $sorted ) )
		{
			$tmp_result = array( ) ;
			foreach( $result as $r )
			{
				$tmp_result[ $r->msgno ] = $r ;
			}

			$result = array( ) ;
			foreach( $msgs as $msgno )
			{
				$result[ ] = $tmp_result[ $msgno ] ;
			}
		}

		$return = array( 'res' => $result ,
			'start' => $start ,
			'limit' => $limit ,
			'sorting' => array( 'by' => $sort[ 0 ] , 'direction' => $sort[ 1 ] ) ,
			'total' => imap_num_msg( $this->marubox ) ) ;
		$return[ 'pages' ] = ceil( $return[ 'total' ] / $per_page ) ;
		return $return ;
	}

	// 获取头部
	function getHeaders( $mid ) // Get Header info
	{
		if( !$this->marubox )
			return false ;

		$mail_header = imap_header( $this->marubox , $mid ) ;
		$sender = $mail_header->from[ 0 ] ;
		$sender_replyto = $mail_header->reply_to[ 0 ] ;
		if( strtolower( $sender->mailbox ) != 'mailer-daemon' && strtolower( $sender->mailbox ) != 'postmaster' )
		{
			$mail_details = array(
				'from' => strtolower( $sender->mailbox ) . '@' . $sender->host ,
				'fromName' => $sender->personal ,
				'toOth' => strtolower( $sender_replyto->mailbox ) . '@' . $sender_replyto->host ,
				'toNameOth' => $sender_replyto->personal ,
				'subject' => $mail_header->subject ,
				'to' => strtolower( $mail_header->toaddress )
			) ;
		}
		return $mail_details ;
	}

	function get_mime_type( &$structure ) //Get Mime type Internal Private Use
	{
		$primary_mime_type = array( "TEXT" , "MULTIPART" , "MESSAGE" , "APPLICATION" ,
			"AUDIO" , "IMAGE" , "VIDEO" , "OTHER" ) ;

		if( $structure->subtype )
		{
			return $primary_mime_type[ ( int ) $structure->type ] . '/' . $structure->subtype ;
		}
		return "TEXT/PLAIN" ;
	}

	function get_part( $stream , $msg_number , $mime_type , $structure = false ,
		$part_number = false ) //Get Part Of Message Internal Private Use
	{
		if( !$structure )
		{
			$structure = imap_fetchstructure( $stream , $msg_number ) ;
		}
		if( $structure )
		{
			if( $mime_type == $this->get_mime_type( $structure ) )
			{
				if( !$part_number )
				{
					$part_number = "1" ;
				}
				$text = imap_fetchbody( $stream , $msg_number , $part_number ) ;
				if( $structure->encoding == 3 )
				{
					return imap_base64( $text ) ;
				}
				else if( $structure->encoding == 4 )
				{
					return imap_qprint( $text ) ;
				}
				else
				{
					return $text ;
				}
			}
			if( $structure->type == 1 ) /* multipart */
			{
				while( list($index , $sub_structure) = each( $structure->parts ) )
				{
					if( $part_number )
					{
						$prefix = $part_number . '.' ;
					}
					$data = $this->get_part( $stream , $msg_number , $mime_type , $sub_structure , $prefix . ($index + 1) ) ;
					if( $data )
					{
						return $data ;
					}
				}
			}
		}
		return false ;
	}

	// 获取所有未读邮件数
	function getTotalMails() //Get Total Number off Unread Email In Mailbox
	{
		if( !$this->marubox )
			return false ;

		$headers = imap_headers( $this->marubox ) ;
		return count( $headers ) ;
	}

	// 获取附件
	function GetAttach( $mid , $path ) // Get Atteced File from Mail
	{
		if( !$this->marubox )
		{
			return false ;
		}

		$struckture = imap_fetchstructure( $this->marubox , $mid ) ;
		$ar = "" ;
		if( $struckture->parts )
		{
			foreach( $struckture->parts as $key => $value )
			{
				$enc = $struckture->parts[ $key ]->encoding ;
				if( $struckture->parts[ $key ]->ifdparameters )
				{
					$name = $struckture->parts[ $key ]->dparameters[ 0 ]->value ;
					$message = imap_fetchbody( $this->marubox , $mid , $key + 1 ) ;
					switch( $enc )
					{
						case 0:
							$message = imap_8bit( $message ) ;
							break ;
						case 1:
							$message = imap_8bit( $message ) ;
							break ;
						case 2:
							$message = imap_binary( $message ) ;
							break ;
						case 3:
							$message = imap_base64( $message ) ;
							break ;
						case 4:
							$message = quoted_printable_decode( $message ) ;
							break ;
						case 5:
							$message = $message ;
							break ;
					}
					$fp = fopen( $path . $name , "w" ) ;
					fwrite( $fp , $message ) ;
					fclose( $fp ) ;
					$ar = $ar . $name . "," ;
				}
				// Support for embedded attachments starts here
				if( $struckture->parts[ $key ]->parts )
				{
					foreach( $struckture->parts[ $key ]->parts as $keyb => $valueb )
					{
						$enc = $struckture->parts[ $key ]->parts[ $keyb ]->encoding ;
						if( $struckture->parts[ $key ]->parts[ $keyb ]->ifdparameters )
						{
							$name = $struckture->parts[ $key ]->parts[ $keyb ]->dparameters[ 0 ]->value ;
							$partnro = ($key + 1) . "." . ($keyb + 1) ;
							$message = imap_fetchbody( $this->marubox , $mid , $partnro ) ;
							switch( $enc )
							{
								case 0:
									$message = imap_8bit( $message ) ;
									break ;
								case 1:
									$message = imap_8bit( $message ) ;
									break ;
								case 2:
									$message = imap_binary( $message ) ;
									break ;
								case 3:
									$message = imap_base64( $message ) ;
									break ;
								case 4:
									$message = quoted_printable_decode( $message ) ;
									break ;
								case 5:
									$message = $message ;
									break ;
							}
							$fp = fopen( $path . $name , "w" ) ;
							fwrite( $fp , $message ) ;
							fclose( $fp ) ;
							$ar = $ar . $name . "," ;
						}
					}
				}
			}
		}
		$ar = substr( $ar , 0 , (strlen( $ar ) - 1 ) ) ;
		return $ar ;
	}

	// 获取body
	function getBody( $mid ) // Get Message Body
	{
		if( !$this->marubox )
		{
			return false ;
		}
		$body = $this->get_part( $this->marubox , $mid , "TEXT/HTML" ) ;
		if( $body == "" )
		{
			$body = $this->get_part( $this->marubox , $mid , "TEXT/PLAIN" ) ;
		}
		if( $body == "" )
		{
			return "" ;
		}
		return $this->_iconv_utf8( $body ) ;
	}

	// 删除邮件
	function deleteMails( $mid ) // Delete That Mail
	{
		if( !$this->marubox )
			return false ;

		imap_delete( $this->marubox , $mid ) ;
	}

	function close_mailbox() //Close Mail Box
	{
		if( !$this->marubox )
			return false ;

		imap_close( $this->marubox , CL_EXPUNGE ) ;
	}

	function _imap_utf8( $text )
	{
		if( preg_match( '/=\?([a-zA-z0-9\-]+)\?(.*)\?=/i' , $text , $match ) )
		{
			$text = imap_utf8( $text ) ;
			if( strtolower( substr( $match[ 1 ] , 0 , 2 ) ) == 'gb' )
			{
				$text = iconv( 'gbk' , 'utf-8' , $text ) ;
			}
			return $text ;
		}
		return $this->_iconv_utf8( $text ) ;
	}

	function _iconv_utf8( $text )
	{
		$s1 = iconv( 'gbk' , 'utf-8' , $text ) ;
		$s0 = iconv( 'utf-8' , 'gbk' , $s1 ) ;
		if( $s0 == $text )
		{
			return $s1 ;
		}
		else
		{
			return $text ;
		}
	}

}